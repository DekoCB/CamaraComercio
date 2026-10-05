<?php

namespace Database\Factories;

use App\Models\RentalCatalogItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RentalCatalogItem>
 */
class RentalCatalogItemFactory extends Factory
{
    protected $model = RentalCatalogItem::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'default_hourly_rate' => fake()->randomFloat(2, 10, 100),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 20),
        ];
    }
}
