<?php

namespace App\Services;

use App\Models\Invoice;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Editing/anulación of a single already-generated invoice — the client's
 * demo feedback (acta2.txt [10:xx]) asked for a way to fix a mistaken
 * factura. Mirrors PaymentService::void()'s rule (docs/OPEN_BUSINESS_
 * DECISIONS.md #21): never edited/deleted once money has moved against
 * it, only voided, kept for the audit trail. Since paid_total must be
 * exactly 0 for either operation, there is no balance to recompute here.
 */
class InvoiceService
{
    /**
     * @param  array{receipt_number?: ?string, amount: float, issue_date: CarbonInterface, due_date: CarbonInterface}  $data
     */
    public function update(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data) {
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            $this->assertEditable($locked);

            $locked->update([
                'receipt_number' => $data['receipt_number'] ?? null,
                'amount' => $data['amount'],
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
            ]);

            return $locked;
        });
    }

    public function void(Invoice $invoice, string $reason, int $voidedBy): Invoice
    {
        return DB::transaction(function () use ($invoice, $reason, $voidedBy) {
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            $this->assertEditable($locked);

            $locked->update([
                'voided_at' => now(),
                'voided_by' => $voidedBy,
                'void_reason' => $reason,
            ]);

            return $locked;
        });
    }

    private function assertEditable(Invoice $invoice): void
    {
        if ($invoice->isVoided()) {
            throw new InvalidArgumentException('Esta factura ya fue anulada.');
        }

        if ((float) $invoice->paid_total > 0) {
            throw new InvalidArgumentException('No se puede modificar una factura con pagos registrados. Anule los pagos primero.');
        }
    }
}
