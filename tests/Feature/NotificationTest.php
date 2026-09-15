<?php

namespace Tests\Feature;

use App\Models\Associate;
use App\Models\Invoice;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_generating_invoices_creates_a_notification(): void
    {
        Associate::factory()->create(['is_active' => true]);
        $user = $this->userWithPermissions(['billing.generate']);

        $this->actingAs($user)->post('/invoices/generate', [
            'period' => '2026-09',
            'amount' => '250.00',
            'issue_date' => '2026-09-01',
            'due_date' => '2026-09-30',
            'confirm' => '1',
        ]);

        $this->assertDatabaseHas('notifications', ['type' => Notification::TYPE_INVOICE_GENERATED]);
    }

    public function test_generating_invoices_for_a_period_with_no_active_associates_does_not_notify(): void
    {
        $user = $this->userWithPermissions(['billing.generate']);

        $this->actingAs($user)->post('/invoices/generate', [
            'period' => '2026-09',
            'amount' => '250.00',
            'issue_date' => '2026-09-01',
            'due_date' => '2026-09-30',
            'confirm' => '1',
        ]);

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_voiding_a_payment_creates_a_notification(): void
    {
        $invoice = Invoice::factory()->create(['amount' => 500, 'paid_total' => 0]);
        $collector = $this->userWithPermissions(['payments.register']);
        $admin = $this->userWithPermissions(['payments.void']);

        $this->actingAs($collector)->post("/invoices/{$invoice->id}/payments", ['amount' => '200.00', 'paid_at' => now()->toDateString(), 'method' => 'EFECTIVO']);
        $payment = $invoice->fresh()->payments->first();

        $this->actingAs($admin)->put("/payments/{$payment->id}/void", ['reason' => 'Monto ingresado por error']);

        $this->assertDatabaseHas('notifications', [
            'type' => Notification::TYPE_PAYMENT_VOIDED,
            'entity_id' => (string) $payment->id,
        ]);
    }

    public function test_importing_associates_does_not_create_a_notification(): void
    {
        $user = $this->userWithPermissions(['associates.manage']);

        // Reuses the same xlsx-building helper pattern as AssociateImportTest.
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['Nombre'], ['Alguien Nuevo']], null, 'A1');
        $path = tempnam(sys_get_temp_dir(), 'import').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $file = new UploadedFile($path, 'asociados.xlsx', null, null, true);

        $this->actingAs($user)->post('/associates/import/preview', ['file' => $file]);
        $this->actingAs($user)->post('/associates/import/confirm');

        $this->assertDatabaseCount('notifications', 0);

        Storage::deleteDirectory('imports');
    }

    public function test_unread_count_only_counts_notifications_this_user_has_not_read(): void
    {
        Notification::record(Notification::TYPE_PAYMENT_VOIDED, 'Uno');
        Notification::record(Notification::TYPE_PAYMENT_VOIDED, 'Dos');
        $user = $this->userWithPermissions([]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('notification-badge">2<', false);
    }

    public function test_mark_all_read_clears_the_unread_badge_for_that_user_only(): void
    {
        Notification::record(Notification::TYPE_PAYMENT_VOIDED, 'Uno');
        Notification::record(Notification::TYPE_PAYMENT_VOIDED, 'Dos');
        $userA = $this->userWithPermissions([]);
        $userB = $this->userWithPermissions([]);

        $this->actingAs($userA)->post('/notifications/read-all');

        $this->assertTrue(Notification::first()->fresh()->isReadBy($userA->id));
        $this->assertFalse(Notification::first()->fresh()->isReadBy($userB->id));
    }

    public function test_guest_cannot_mark_notifications_read(): void
    {
        $response = $this->post('/notifications/read-all');

        $response->assertRedirect('/login');
    }
}
