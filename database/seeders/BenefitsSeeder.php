<?php

namespace Database\Seeders;

use App\Models\Benefit;
use Illuminate\Database\Seeder;

/**
 * acta2.txt [~15:30]: the only benefit the client described in the demo.
 * There's no catalog-management screen yet (out of scope — the ask was
 * tracking usage, not administering benefit types), so a second benefit
 * for now means adding another updateOrCreate() call here, or one row
 * via tinker/DB client.
 */
class BenefitsSeeder extends Seeder
{
    public function run(): void
    {
        Benefit::updateOrCreate(
            ['name' => 'Uso de auditorio'],
            ['description' => 'Uso gratuito anual del auditorio de la Cámara.', 'annual_quota' => 1, 'is_active' => true]
        );
    }
}
