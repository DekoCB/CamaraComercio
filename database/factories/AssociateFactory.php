<?php

namespace Database\Factories;

use App\Models\Associate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Associate>
 */
class AssociateFactory extends Factory
{
    protected $model = Associate::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'company' => fake()->companySuffix(),
            'contact_phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'is_active' => true,
        ];
    }
}
