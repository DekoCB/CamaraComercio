<?php

namespace Tests\Feature;

use App\Models\Associate;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class PaymentMethodTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_registering_a_payment_stores_its_method_and_rejects_unknown_ones(): void
    {
        $invoice = Invoice::factory()->create(['amount' => 200, 'paid_total' => 0]);
        $user = $this->userWithPermissions(['payments.register']);

        $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
            'amount' => '100.00', 'paid_at' => now()->toDateString(), 'method' => 'YAPE',
        ])->assertRedirect();
        $this->assertDatabaseHas('payments', ['invoice_id' => $invoice->id, 'method' => 'YAPE']);

        $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
            'amount' => '50.00', 'paid_at' => now()->toDateString(), 'method' => 'CRIPTO',
        ])->assertSessionHasErrors('method');

        $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
            'amount' => '50.00', 'paid_at' => now()->toDateString(),
        ])->assertSessionHasErrors('method');
    }

    public function test_registering_a_payment_stores_its_optional_operation_number(): void
    {
        $invoice = Invoice::factory()->create(['amount' => 200, 'paid_total' => 0]);
        $user = $this->userWithPermissions(['payments.register']);

        $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
            'amount' => '100.00', 'paid_at' => now()->toDateString(), 'method' => 'TRANSFERENCIA',
            'operation_number' => 'OP-00123',
        ])->assertRedirect();
        $this->assertDatabaseHas('payments', ['invoice_id' => $invoice->id, 'operation_number' => 'OP-00123']);

        $this->actingAs($user)->post("/invoices/{$invoice->id}/payments", [
            'amount' => '50.00', 'paid_at' => now()->toDateString(), 'method' => 'EFECTIVO',
        ])->assertRedirect();
        $this->assertDatabaseHas('payments', ['invoice_id' => $invoice->id, 'amount' => 50, 'operation_number' => null]);
    }

    public function test_both_payment_tabs_can_be_filtered_by_method(): void
    {
        $yape = Associate::factory()->create(['name' => 'Paga Con Yape SAC']);
        $cash = Associate::factory()->create(['name' => 'Paga En Efectivo SAC']);
        $legacy = Associate::factory()->create(['name' => 'Pago Antiguo SAC']);
        $invYape = Invoice::factory()->for($yape)->create(['period' => '2026-08', 'amount' => 100, 'paid_total' => 100, 'status' => Invoice::STATUS_PAGADA]);
        $invCash = Invoice::factory()->for($cash)->create(['period' => '2026-08', 'amount' => 100, 'paid_total' => 100, 'status' => Invoice::STATUS_PAGADA]);
        $invLegacy = Invoice::factory()->for($legacy)->create(['period' => '2026-08', 'amount' => 100, 'paid_total' => 100, 'status' => Invoice::STATUS_PAGADA]);
        Payment::factory()->create(['invoice_id' => $invYape->id, 'amount' => 100, 'method' => 'YAPE']);
        Payment::factory()->create(['invoice_id' => $invCash->id, 'amount' => 100, 'method' => 'EFECTIVO']);
        Payment::factory()->create(['invoice_id' => $invLegacy->id, 'amount' => 100, 'method' => null]);
        $user = $this->userWithPermissions(['payments.register']);

        // Cuotas tab: invoices paid through the channel
        $this->actingAs($user)->get('/payments?method=YAPE')
            ->assertOk()->assertSee('Paga Con Yape SAC')->assertDontSee('Paga En Efectivo SAC')->assertDontSee('Pago Antiguo SAC')
            ->assertSee('Método de pago');
        $this->actingAs($user)->get('/payments?method=sin_metodo')
            ->assertOk()->assertSee('Pago Antiguo SAC')->assertDontSee('Paga Con Yape SAC');

        // Movimientos tab: the payments themselves
        $this->actingAs($user)->get('/payments?tab=movimientos&method=EFECTIVO')
            ->assertOk()->assertSee('Paga En Efectivo SAC')->assertDontSee('Paga Con Yape SAC')->assertSee('Efectivo');

        // Unknown value is ignored, not an error
        $this->actingAs($user)->get('/payments?method=BITCOIN')->assertOk()->assertSee('Paga Con Yape SAC')->assertSee('Paga En Efectivo SAC');
    }

    public function test_excel_import_reads_an_optional_method_column(): void
    {
        $associate = Associate::factory()->create(['name' => 'Importado SAC']);
        $invoice = Invoice::factory()->for($associate)->create(['period' => '2026-08', 'amount' => 300, 'paid_total' => 0]);
        $user = $this->userWithPermissions(['payments.register']);

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([
            ['Asociado', 'Periodo de factura', 'Monto', 'Fecha de pago', 'Método de pago'],
            [$associate->name, '2026-08', '100.00', '2026-08-15', 'transferencia'],
            [$associate->name, '2026-08', '100.00', '2026-08-16', 'Plin'],
            [$associate->name, '2026-08', '100.00', '2026-08-17', 'trueque'],
        ], null, 'A1');
        $path = tempnam(sys_get_temp_dir(), 'pm').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $file = new UploadedFile($path, 'pagos.xlsx', null, null, true);

        $this->actingAs($user)->post('/payments/import/preview', ['file' => $file])
            ->assertOk()
            ->assertSee('Transferencia bancaria')
            ->assertSee('Método de pago no reconocido');

        $this->actingAs($user)->post('/payments/import/confirm')->assertRedirect();

        $this->assertDatabaseHas('payments', ['invoice_id' => $invoice->id, 'method' => 'TRANSFERENCIA']);
        $this->assertDatabaseHas('payments', ['invoice_id' => $invoice->id, 'method' => 'PLIN']);
        $this->assertSame(2, Payment::where('invoice_id', $invoice->id)->count());
    }
}
