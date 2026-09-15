<?php

namespace Tests\Feature;

use App\Models\Associate;
use App\Models\Invoice;
use App\Models\Payment;
use Database\Seeders\DemoMassiveSeeder;
use Database\Seeders\RolesPermissionsModulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoMassiveSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_builds_a_consistent_dataset_and_does_not_duplicate_on_rerun(): void
    {
        $this->seed(RolesPermissionsModulesSeeder::class);
        $this->seed(DemoMassiveSeeder::class);

        $associates = Associate::where('email', 'like', '%@'.DemoMassiveSeeder::DEMO_DOMAIN)->count();
        $this->assertSame(320, $associates);
        $this->assertGreaterThan(5000, Invoice::count());
        $this->assertGreaterThan(5000, Payment::count());
        $this->assertGreaterThan(0, Payment::whereNotNull('voided_at')->count());
        $this->assertGreaterThan(0, Payment::where('method', 'YAPE')->count());
        $this->assertGreaterThan(0, Associate::where('status', Associate::STATUS_SUSPENDIDO)->count());

        // invoices.paid_total must equal the sum of non-voided payments
        $mismatch = DB::table('invoices as i')
            ->leftJoin(DB::raw('(select invoice_id, coalesce(sum(amount), 0) as s from payments where voided_at is null group by invoice_id) p'), 'p.invoice_id', '=', 'i.id')
            ->whereRaw('abs(coalesce(p.s, 0) - i.paid_total) > 0.005')
            ->count();
        $this->assertSame(0, $mismatch);

        // and the stored status must follow from it
        $badStatus = Invoice::query()
            ->whereRaw("(status = 'PAGADA' and paid_total < amount) or (status = 'PENDIENTE' and paid_total > 0) or (status = 'PARCIAL' and (paid_total = 0 or paid_total >= amount))")
            ->count();
        $this->assertSame(0, $badStatus);

        $this->seed(DemoMassiveSeeder::class);
        $this->assertSame($associates, Associate::where('email', 'like', '%@'.DemoMassiveSeeder::DEMO_DOMAIN)->count());
    }
}
