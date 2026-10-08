<?php

namespace App\Services;

use App\Models\PlateIssuance;
use App\Models\Setting;
use Carbon\CarbonImmutable;

/**
 * Emisión de placas — ver PlateIssuance para el porqué de no admitir
 * edición ni cancelación. register() sigue el mismo patrón que
 * ProtestService::register().
 */
class PlateIssuanceService
{
    /**
     * @param  array{procedure_type: string, other_description?: ?string, plate_number?: ?string, associate_id?: ?int, client_name?: ?string, vehicle_description?: ?string, receipt_type: string, receipt_number?: ?string, amount: float, issued_at: \DateTimeInterface, notes?: ?string}  $data
     */
    public function register(array $data, int $userId): PlateIssuance
    {
        return PlateIssuance::create([
            'procedure_type' => $data['procedure_type'],
            'other_description' => $data['procedure_type'] === PlateIssuance::PROCEDURE_OTROS ? ($data['other_description'] ?? null) : null,
            'plate_number' => isset($data['plate_number']) && $data['plate_number'] !== '' ? strtoupper((string) $data['plate_number']) : null,
            'associate_id' => $data['associate_id'] ?? null,
            'client_name' => $data['client_name'] ?? null,
            'vehicle_description' => $data['vehicle_description'] ?? null,
            'receipt_type' => $data['receipt_type'],
            'receipt_number' => $data['receipt_number'] ?? null,
            'amount' => $data['amount'],
            'issued_at' => $data['issued_at'],
            'notes' => $data['notes'] ?? null,
            'registered_by' => $userId,
        ]);
    }

    /** @return array<string, ?float> tarifa por defecto de cada tipo de trámite, clave = procedure_type */
    public function rates(): array
    {
        return collect(PlateIssuance::PROCEDURE_TYPES)->keys()
            ->mapWithKeys(function (string $type) {
                $value = Setting::get(PlateIssuance::rateSettingKey($type));

                return [$type => $value !== null && $value !== '' ? (float) $value : null];
            })->all();
    }

    /**
     * "Teniendo también su respectivo reporte" (pedido del cliente):
     * volumen y monto cobrado por tipo y por comprobante, para el mes
     * dado — mismo resumen que ya tenía Protestos en su propia pantalla.
     *
     * @return array{total: int, totalAmount: float, byProcedure: array<string, int>, byReceiptType: array<string, int>}
     */
    public function monthlySummary(?CarbonImmutable $month = null): array
    {
        $month ??= CarbonImmutable::now();
        $start = $month->startOfMonth();
        $end = $month->endOfMonth();

        $records = PlateIssuance::whereBetween('issued_at', [$start->toDateString(), $end->toDateString()])->get();

        return [
            'total' => $records->count(),
            'totalAmount' => (float) $records->sum('amount'),
            'byProcedure' => $records->groupBy('procedure_type')->map->count()->all(),
            'byReceiptType' => $records->groupBy('receipt_type')->map->count()->all(),
        ];
    }
}
