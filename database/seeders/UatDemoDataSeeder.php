<?php

namespace Database\Seeders;

use App\Models\Associate;
use App\Models\Invoice;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Fictitious data covering every invoice state (PENDIENTE/PARCIAL/PAGADA/
 * VENCIDA) plus an inactive associate — section 19 of the 2026-08-19
 * production-readiness audit found the existing AssociateSeeder produced
 * associates but zero invoices/payments, which isn't representative for
 * UAT (docs/UAT_PLAN.md) or for manually exercising the dashboard/
 * cartera/reportes screens against something other than an empty state.
 * Never real data (section 34 of the functional spec) — same fictitious
 * companies AssociateSeeder already uses, extended with an inactive one.
 *
 * Payments go through PaymentService::register() rather than direct
 * Payment::create(), so paid_total/status stay in sync exactly the way
 * they would from the real UI — no separate/duplicated calculation here.
 */
class UatDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::first();
        $registeredBy = $admin?->id;
        $paymentService = app(PaymentService::class);

        $active = Associate::where('is_active', true)->get();
        if ($active->isEmpty() || $active->count() < 3) {
            return; // AssociateSeeder didn't run first — nothing to attach invoices to
        }

        // One inactive associate — section 19 explicitly asks for this,
        // and it's the case that proves an inactive associate never
        // receives invoices from InvoiceGenerationService (docs/
        // OPEN_BUSINESS_DECISIONS.md pregunta 4).
        $inactive = Associate::updateOrCreate(
            ['email' => 'inactivo@example.com'],
            [
                'name' => 'Zapatería El Paso (inactivo)',
                'company' => 'El Paso Calzados',
                'contact_phone' => '555-0199',
                'is_active' => false,
            ]
        );

        $today = Carbon::today();
        $currentPeriod = $today->format('Y-m');
        $lastPeriod = $today->copy()->subMonthNoOverflow()->format('Y-m');
        $overduePeriod = $today->copy()->subMonths(2)->format('Y-m');

        $amount = 250.00;

        [$a1, $a2, $a3] = [$active[0], $active[1] ?? $active[0], $active[2] ?? $active[0]];

        // PENDIENTE: current period, due date still in the future, no payment.
        Invoice::updateOrCreate(
            ['associate_id' => $a1->id, 'period' => $currentPeriod],
            [
                'amount' => $amount,
                'paid_total' => 0,
                'issue_date' => $today->copy()->startOfMonth(),
                'due_date' => $today->copy()->addDays(15),
                'status' => Invoice::STATUS_PENDIENTE,
                'created_by' => $registeredBy,
            ]
        );

        // PARCIAL: last period, partially paid.
        $partial = Invoice::updateOrCreate(
            ['associate_id' => $a2->id, 'period' => $lastPeriod],
            [
                'amount' => $amount,
                'paid_total' => 0,
                'issue_date' => $today->copy()->subMonthNoOverflow()->startOfMonth(),
                'due_date' => $today->copy()->subMonthNoOverflow()->addDays(15),
                'status' => Invoice::STATUS_PENDIENTE,
                'created_by' => $registeredBy,
            ]
        );
        if ($registeredBy && $partial->wasRecentlyCreated) {
            $paymentService->register($partial, 100.00, $today->copy()->subDays(3), $registeredBy, 'Pago parcial de demostración (UAT)');
        }

        // PAGADA: two periods ago, fully paid.
        $paid = Invoice::updateOrCreate(
            ['associate_id' => $a3->id, 'period' => $overduePeriod],
            [
                'amount' => $amount,
                'paid_total' => 0,
                'issue_date' => $today->copy()->subMonths(2)->startOfMonth(),
                'due_date' => $today->copy()->subMonths(2)->addDays(15),
                'status' => Invoice::STATUS_PENDIENTE,
                'created_by' => $registeredBy,
            ]
        );
        if ($registeredBy && $paid->wasRecentlyCreated) {
            $paymentService->register($paid, $amount, $today->copy()->subMonths(2)->addDays(10), $registeredBy, 'Pago completo de demostración (UAT)');
        }

        // VENCIDA: two periods ago, unpaid, due_date already in the past —
        // status column stays PENDIENTE (never persisted as VENCIDA, see
        // Invoice::effectiveStatus()); it reads as vencida because its
        // due_date has passed.
        Invoice::updateOrCreate(
            ['associate_id' => $a1->id, 'period' => $overduePeriod],
            [
                'amount' => $amount,
                'paid_total' => 0,
                'issue_date' => $today->copy()->subMonths(2)->startOfMonth(),
                'due_date' => $today->copy()->subMonths(2)->addDays(15),
                'status' => Invoice::STATUS_PENDIENTE,
                'created_by' => $registeredBy,
            ]
        );

        $this->command?->info('UatDemoDataSeeder: associate inactivo #'.$inactive->id.' + facturas PENDIENTE/PARCIAL/PAGADA/VENCIDA creadas.');
    }
}
