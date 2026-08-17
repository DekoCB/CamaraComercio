<?php

namespace App\Services;

use App\Models\Associate;
use App\Models\Invoice;
use Carbon\CarbonInterface;
use Throwable;

/**
 * HU-06: generates one invoice per active associate for a period,
 * skipping associates that already have an invoice for that period
 * (the associate_id+period unique constraint is the ultimate guard;
 * this pre-check just avoids relying on catching that constraint
 * violation for the common case) and recording any per-associate
 * failure without aborting the whole batch.
 */
class InvoiceGenerationService
{
    /**
     * @return array{created: int, skipped: int, errors: array<int, array{associate_id: int, associate_name: string, message: string}>}
     */
    public function generateForPeriod(
        string $period,
        float $amount,
        CarbonInterface $issueDate,
        CarbonInterface $dueDate,
        ?int $createdBy
    ): array {
        $associates = Associate::where('is_active', true)->orderBy('name')->get();

        $alreadyInvoiced = Invoice::forPeriod($period)->pluck('associate_id')->all();

        $created = 0;
        $skipped = 0;
        $errors = [];

        foreach ($associates as $associate) {
            if (in_array($associate->id, $alreadyInvoiced, true)) {
                $skipped++;

                continue;
            }

            try {
                Invoice::create([
                    'associate_id' => $associate->id,
                    'period' => $period,
                    'amount' => $amount,
                    'paid_total' => 0,
                    'issue_date' => $issueDate,
                    'due_date' => $dueDate,
                    'status' => Invoice::STATUS_PENDIENTE,
                    'created_by' => $createdBy,
                ]);
                $created++;
            } catch (Throwable $e) {
                $errors[] = [
                    'associate_id' => $associate->id,
                    'associate_name' => $associate->name,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return compact('created', 'skipped', 'errors');
    }
}
