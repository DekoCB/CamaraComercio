<?php

namespace Database\Factories;

use App\Models\Protest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Protest>
 */
class ProtestFactory extends Factory
{
    protected $model = Protest::class;

    public function definition(): array
    {
        return [
            'type' => Protest::TYPE_PROTESTO,
            'channel' => Protest::CHANNEL_NOTARIAL,
            'instrument_type' => Protest::INSTRUMENT_LETRA_CAMBIO,
            'debtor_name' => fake()->company(),
            'debtor_document' => fake()->numerify('########'),
            'creditor_name' => fake()->company(),
            'creditor_document' => fake()->numerify('###########'),
            'associate_id' => null,
            'amount' => fake()->randomFloat(2, 20, 200),
            'registered_at' => fake()->dateTimeBetween('-2 months', 'now'),
            'status' => Protest::STATUS_REGISTRADO,
        ];
    }
}
