<?php

namespace App\Services;

use App\Models\Associate;
use App\Models\Benefit;
use App\Models\BenefitUsage;
use DateTimeInterface;
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
