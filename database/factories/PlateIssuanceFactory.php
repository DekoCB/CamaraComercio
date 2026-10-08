<?php

namespace Database\Factories;

use App\Models\PlateIssuance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlateIssuance>
 */
class PlateIssuanceFactory extends Factory
{
    protected $model = PlateIssuance::class;

    public function definition(): array
    {
        return [
            'procedure_type' => PlateIssuance::PROCEDURE_NUEVA,
            'plate_number' => strtoupper(fake()->bothify('???-###')),
            'associate_id' => null,
            'client_name' => fake()->name(),
            'vehicle_description' => fake()->randomElement(['Toyota Yaris', 'Hyundai Accent', 'Kia Rio']),
            'receipt_type' => PlateIssuance::RECEIPT_BOLETA,
            'amount' => fake()->randomFloat(2, 20, 200),
            'issued_at' => fake()->dateTimeBetween('-2 months', 'now'),
        ];
    }
}
