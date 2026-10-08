<?php

namespace Database\Factories;

use App\Models\PaymentRequisition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentRequisition>
 */
class PaymentRequisitionFactory extends Factory
{
    protected $model = PaymentRequisition::class;

    public function definition(): array
    {
        return [
            'type' => PaymentRequisition::TYPE_REEMBOLSO,
            'year' => (int) now()->year,
            'sequence' => fake()->unique()->numberBetween(1, 999999),
            'requester_area' => 'Logística y Operaciones',
            'recipient_name' => 'Klaus Castro Pimentel',
            'recipient_role' => 'Gerente General de Cámara de Comercio de Huancayo',
            'subject' => fake()->sentence(),
            'issued_at' => fake()->dateTimeBetween('-2 months', 'now'),
            'beneficiary_name' => fake()->name(),
        ];
    }
}
