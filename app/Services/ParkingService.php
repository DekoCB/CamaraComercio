<?php

namespace App\Services;

use App\Models\ParkingSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Control de estacionamiento — ver ParkingSession para el porqué de no
 * separar entrada/salida en dos tablas. register()/checkout() siguen el
 * mismo patrón transaccional que ProtestService/RentalService.
 */
class ParkingService
{
    /**
     * @param  array{plate: string, associate_id?: ?int, owner_name?: ?string, vehicle_description?: ?string, entered_at: \DateTimeInterface, amount?: ?float, notes?: ?string}  $data
     */
    public function register(array $data, int $userId): ParkingSession
    {
        return ParkingSession::create([
            'plate' => strtoupper((string) $data['plate']),
            'associate_id' => $data['associate_id'] ?? null,
            'owner_name' => $data['owner_name'] ?? null,
            'vehicle_description' => $data['vehicle_description'] ?? null,
            'entered_at' => $data['entered_at'],
            'amount' => $data['amount'] ?? null,
            'notes' => $data['notes'] ?? null,
            'registered_by' => $userId,
        ]);
    }

    /**
     * @param  array{plate: string, associate_id?: ?int, owner_name?: ?string, vehicle_description?: ?string, entered_at: \DateTimeInterface, amount?: ?float, notes?: ?string}  $data
     */
    public function update(ParkingSession $session, array $data): ParkingSession
    {
        if (! $session->isParked()) {
            throw new InvalidArgumentException('Ya se registró la salida de este vehículo — no se puede editar.');
        }

        $session->update([
            'plate' => strtoupper((string) $data['plate']),
            'associate_id' => $data['associate_id'] ?? null,
            'owner_name' => $data['owner_name'] ?? null,
            'vehicle_description' => $data['vehicle_description'] ?? null,
            'entered_at' => $data['entered_at'],
            'amount' => $data['amount'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return $session;
    }

    public function checkout(ParkingSession $session, string|\DateTimeInterface $exitedAt, ?float $amount): ParkingSession
    {
        return DB::transaction(function () use ($session, $exitedAt, $amount) {
            $locked = ParkingSession::whereKey($session->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isParked()) {
                throw new InvalidArgumentException('Ya se había registrado la salida de este vehículo.');
            }

            $locked->update([
                'exited_at' => $exitedAt,
                'amount' => $amount ?? $locked->amount,
            ]);

            return $locked;
        });
    }

    /**
     * @return array{total: int, totalAmount: float, stillParked: int}
     */
    public function monthlySummary(?CarbonImmutable $month = null): array
    {
        $month ??= CarbonImmutable::now();
        $start = $month->startOfMonth();
        $end = $month->endOfMonth();

        $records = ParkingSession::whereBetween('entered_at', [$start, $end])->get();

        return [
            'total' => $records->count(),
            'totalAmount' => (float) $records->sum('amount'),
            'stillParked' => ParkingSession::whereNull('exited_at')->count(),
        ];
    }
}
