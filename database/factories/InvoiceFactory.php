<?php

namespace Database\Factories;

use App\Models\Associate;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'associate_id' => Associate::factory(),
            'period' => now()->format('Y-m'),
            'amount' => 100,
            'paid_total' => 0,
            'issue_date' => now()->startOfMonth(),
            'due_date' => now()->startOfMonth()->addDays(15),
            'status' => Invoice::STATUS_PENDIENTE,
        ];
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'issue_date' => now()->subMonths(2),
            'due_date' => now()->subMonth(),
        ]);
    }
}
