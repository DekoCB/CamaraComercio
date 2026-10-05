<?php

namespace App\Services;

use App\Models\Associate;
use App\Models\Benefit;
use App\Models\BenefitUsage;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * acta2.txt [~15:30]: track whether an associate already used a benefit
 * (e.g. their yearly free auditorium slot) "en vez de consultarlo
 * manualmente". A usage is registered against the calendar year of its
 * own date, not the date it happens to be recorded, so backfilling a
 * usage from earlier in the year still counts against that year's quota.
 */
class BenefitService
{
    public function usageThisYear(Associate $associate, Benefit $benefit, ?int $year = null): int
    {
        $year ??= now()->year;

        return BenefitUsage::query()
            ->where('associate_id', $associate->id)
            ->where('benefit_id', $benefit->id)
            ->whereYear('used_at', $year)
            ->count();
    }

    /**
     * Para cada asociado activo, cuántos usos le quedan de cada beneficio
     * activo este año — una sola consulta agregada en vez de N+1, ya que
     * esto alimenta una tabla con todos los asociados a la vez.
     */
    public function remainingByAssociate(?int $year = null): Collection
    {
        $year ??= now()->year;
        $benefits = Benefit::where('is_active', true)->orderBy('name')->get();

        $usedCounts = BenefitUsage::query()
            ->whereYear('used_at', $year)
            ->selectRaw('associate_id, benefit_id, count(*) as used')
            ->groupBy('associate_id', 'benefit_id')
            ->get()
            ->groupBy('associate_id')
            ->map(fn ($rows) => $rows->keyBy('benefit_id'));

        return Associate::where('is_active', true)->orderBy('name')->get()
            ->map(function (Associate $associate) use ($benefits, $usedCounts) {
                $remaining = $benefits->mapWithKeys(function (Benefit $benefit) use ($associate, $usedCounts) {
                    $used = $usedCounts->get($associate->id)?->get($benefit->id)?->used ?? 0;

                    return [$benefit->id => max(0, $benefit->annual_quota - $used)];
                });

                return (object) [
                    'associate' => $associate,
                    'remaining' => $remaining,
                    'total' => $remaining->sum(),
                ];
            });
    }

    public function register(Associate $associate, Benefit $benefit, DateTimeInterface $usedAt, ?string $notes, int $registeredBy): BenefitUsage
    {
        return DB::transaction(function () use ($associate, $benefit, $usedAt, $notes, $registeredBy) {
            $year = (int) $usedAt->format('Y');
            $used = $this->usageThisYear($associate, $benefit, $year);

            if ($used >= $benefit->annual_quota) {
                throw new InvalidArgumentException(
                    "\"{$benefit->name}\" ya alcanzó su cupo anual ({$benefit->annual_quota}) para {$year}."
                );
            }

            return BenefitUsage::create([
                'associate_id' => $associate->id,
                'benefit_id' => $benefit->id,
                'used_at' => $usedAt,
                'notes' => $notes,
                'registered_by' => $registeredBy,
            ]);
        });
    }
}
