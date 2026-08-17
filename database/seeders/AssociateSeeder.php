<?php

namespace Database\Seeders;

use App\Models\Associate;
use Illuminate\Database\Seeder;

/**
 * Fictitious sample associates for local development only (section 34
 * of the functional spec explicitly forbids real data here).
 */
class AssociateSeeder extends Seeder
{
    public function run(): void
    {
        $samples = [
            ['name' => 'Comercial Andina SAC', 'company' => 'Comercial Andina', 'contact_phone' => '555-0101', 'email' => 'contacto@andina.example.com'],
            ['name' => 'Distribuidora El Sol', 'company' => 'El Sol Distribuciones', 'contact_phone' => '555-0102', 'email' => 'ventas@elsol.example.com'],
            ['name' => 'Ferretería Central', 'company' => 'Ferretería Central EIRL', 'contact_phone' => '555-0103', 'email' => 'info@ferrcentral.example.com'],
            ['name' => 'Textiles Norte', 'company' => 'Textiles del Norte SA', 'contact_phone' => '555-0104', 'email' => 'admin@textilesnorte.example.com'],
            ['name' => 'Panadería Dulce Hogar', 'company' => 'Dulce Hogar', 'contact_phone' => '555-0105', 'email' => 'pedidos@dulcehogar.example.com'],
        ];

        foreach ($samples as $sample) {
            Associate::updateOrCreate(['email' => $sample['email']], $sample + ['is_active' => true]);
        }
    }
}
