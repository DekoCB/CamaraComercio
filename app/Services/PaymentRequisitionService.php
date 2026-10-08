<?php

namespace App\Services;

use App\Models\PaymentRequisition;
use App\Models\PaymentRequisitionItem;
use Illuminate\Support\Facades\DB;

/**
 * Requerimientos de pago/reembolso — register() asigna el siguiente
 * número de la serie del año bajo bloqueo pesimista, para que dos
 * registros simultáneos nunca terminen con el mismo número (mismo
 * cuidado que RentalService con el cruce de horarios).
 */
class PaymentRequisitionService
{
    /**
     * @param  array{type: string, requester_area?: ?string, recipient_name: string, recipient_role?: ?string, subject: string, issued_at: \DateTimeInterface, beneficiary_name: string, bank_details?: ?string, provider_ruc?: ?string, notes?: ?string, items: array}  $data
     */
    public function register(array $data, int $userId): PaymentRequisition
    {
        return DB::transaction(function () use ($data, $userId) {
            $year = (int) now()->year;

            $nextSequence = (int) PaymentRequisition::where('year', $year)
                ->lockForUpdate()
                ->max('sequence') + 1;

            $requisition = PaymentRequisition::create([
                'type' => $data['type'],
                'year' => $year,
                'sequence' => $nextSequence,
                'requester_area' => $data['requester_area'] ?? null,
                'recipient_name' => $data['recipient_name'],
                'recipient_role' => $data['recipient_role'] ?? null,
                'subject' => $data['subject'],
                'issued_at' => $data['issued_at'],
                'beneficiary_name' => $data['beneficiary_name'],
                'bank_details' => $data['bank_details'] ?? null,
                'provider_ruc' => $data['provider_ruc'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            foreach (array_values($data['items']) as $i => $item) {
                $amount = (float) ($item['amount'] ?? 0);
                if ($amount <= 0) {
                    continue;
                }

                PaymentRequisitionItem::create([
                    'payment_requisition_id' => $requisition->id,
                    'item_date' => ! empty($item['item_date']) ? $item['item_date'] : null,
                    'reference' => $item['reference'] ?? null,
                    'description' => $item['description'] ?? '',
                    'quantity' => ! empty($item['quantity']) ? $item['quantity'] : null,
                    'unit_price' => ! empty($item['unit_price']) ? $item['unit_price'] : null,
                    'amount' => $amount,
                    'sort_order' => $i,
                ]);
            }

            return $requisition;
        });
    }
}
