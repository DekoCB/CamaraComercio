<?php

namespace App\Services;

use App\Models\Protest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Registro de Protestos y Moras — ver Protest para el alcance provisional
 * acordado con el usuario. register()/regularize() siguen el mismo
 * patrón transaccional que InvoiceService/RentalService: bloqueo
 * pesimista, guarda de estado, nunca un borrado físico.
 */
class ProtestService
{
    /**
     * @param  array{type: string, channel: string, instrument_type?: ?string, debtor_name: string, debtor_document?: ?string, creditor_name: string, creditor_document?: ?string, associate_id?: ?int, amount: float, registered_at: \DateTimeInterface, notes?: ?string}  $data
     */
    public function register(array $data, int $userId): Protest
    {
        return Protest::create([
            'type' => $data['type'],
            'channel' => $data['channel'],
            'instrument_type' => $data['instrument_type'] ?? null,
            'debtor_name' => $data['debtor_name'],
            'debtor_document' => $data['debtor_document'] ?? null,
            'creditor_name' => $data['creditor_name'],
            'creditor_document' => $data['creditor_document'] ?? null,
            'associate_id' => $data['associate_id'] ?? null,
            'amount' => $data['amount'],
            'registered_at' => $data['registered_at'],
            'status' => Protest::STATUS_REGISTRADO,
            'notes' => $data['notes'] ?? null,
            'created_by' => $userId,
        ]);
    }

    /**
     * Deja constancia de que el título protestado (o la mora) ya fue
     * pagado — el registro pasa a REGULARIZADO, nunca se elimina.
     */
    public function regularize(Protest $protest, ?string $notes, int $userId): Protest
    {
        return DB::transaction(function () use ($protest, $notes, $userId) {
            $locked = Protest::whereKey($protest->id)->lockForUpdate()->firstOrFail();

            if ($locked->isRegularized()) {
                throw new InvalidArgumentException('Este registro ya fue regularizado.');
            }

            $locked->update([
                'status' => Protest::STATUS_REGULARIZADO,
                'regularized_at' => now(),
                'regularized_by' => $userId,
                'regularization_notes' => $notes,
            ]);

            return $locked;
        });
    }

    /**
     * "Cantidad producida al mes" (acta del cliente): volumen y monto
     * cobrado por tipo y por vía, para el mes dado (el actual por
     * defecto) — el reporte interno pedido en el alcance provisional.
     *
     * @return array{total: int, totalAmount: float, byType: array<string, int>, byChannel: array<string, int>}
     */
    public function monthlyCounts(?CarbonImmutable $month = null): array
    {
        $month ??= CarbonImmutable::now();
        $start = $month->startOfMonth();
        $end = $month->endOfMonth();

        $records = Protest::whereBetween('registered_at', [$start->toDateString(), $end->toDateString()])->get();

        return [
            'total' => $records->count(),
            'totalAmount' => (float) $records->sum('amount'),
            'byType' => $records->groupBy('type')->map->count()->all(),
            'byChannel' => $records->groupBy('channel')->map->count()->all(),
        ];
    }
}
