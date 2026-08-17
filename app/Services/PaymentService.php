<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * HU-08/HU-09: registers a full or partial payment against an invoice
 * and keeps invoices.paid_total/status transactionally consistent —
 * per the data model decision (docs/DATA_MODEL.md), paid_total is a
 * denormalized SUM(payments.amount) maintained here, not recalculated
 * on every read.
 */
class PaymentService
{
    public function register(Invoice $invoice, float $amount, DateTimeInterface $paidAt, int $registeredBy, ?string $notes = null): Payment
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('El monto del pago debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($invoice, $amount, $paidAt, $registeredBy, $notes) {
            // Re-fetch with a row lock so two concurrent payment
            // registrations on the same invoice can't both read a stale
            // balance and jointly overpay it.
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($amount > $locked->balance()) {
                throw new InvalidArgumentException('El pago no puede ser mayor al saldo pendiente de la factura.');
            }

            $payment = Payment::create([
                'invoice_id' => $locked->id,
                'amount' => $amount,
                'paid_at' => $paidAt,
                'registered_by' => $registeredBy,
                'notes' => $notes,
            ]);

            $newPaidTotal = round((float) $locked->paid_total + $amount, 2);
            $locked->update([
                'paid_total' => $newPaidTotal,
                'status' => $this->statusFor((float) $locked->amount, $newPaidTotal),
            ]);

            return $payment;
        });
    }

    private function statusFor(float $amount, float $paidTotal): string
    {
        return match (true) {
            $paidTotal >= $amount => Invoice::STATUS_PAGADA,
            $paidTotal > 0 => Invoice::STATUS_PARCIAL,
            default => Invoice::STATUS_PENDIENTE,
        };
    }
}
