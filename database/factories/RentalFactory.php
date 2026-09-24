<?php

namespace Database\Factories;

use App\Models\Associate;
use App\Models\Rental;
use App\Models\Space;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rental>
 */
class RentalFactory extends Factory
{
    protected $model = Rental::class;

    public function definition(): array
    {
        $starts = fake()->dateTimeBetween('+1 day', '+30 days');

        return [
            'space_id' => Space::factory(),
            'associate_id' => Associate::factory(),
            'starts_at' => $starts,
            'ends_at' => (clone $starts)->modify('+2 hours'),
            'purpose' => fake()->sentence(3),
            'amount' => fake()->randomFloat(2, 50, 500),
            'status' => Rental::STATUS_COTIZADA,
            'notes' => null,
        ];
    }
}
