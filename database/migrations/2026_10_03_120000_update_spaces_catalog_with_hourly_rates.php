<?php

use App\Models\Space;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * El cliente confirmó (oct-2026) que el auditorio genérico sembrado en
     * RolesPermissionsModulesSeeder en realidad son 3 salas distintas, cada
     * una con su propia tarifa por hora, más el proyector como equipo
     * aparte. Renombramos la fila existente en vez de borrarla — ya puede
     * tener alquileres reales apuntando a ella — y agregamos las 2 que
     * faltan.
     */
    public function up(): void
    {
        $existing = Space::where('name', 'Auditorio')->first();
        if ($existing) {
            $existing->update(['name' => 'Auditorio Mayor', 'default_rate' => 250.00]);
        } else {
            Space::updateOrCreate(['name' => 'Auditorio Mayor'], ['default_rate' => 250.00, 'is_active' => true]);
        }

        Space::updateOrCreate(['name' => 'Auditorio Menor'], [
            'description' => 'Sala del auditorio menor de la Cámara de Comercio de Huancayo.',
            'default_rate' => 180.00,
            'is_active' => true,
        ]);

        Space::updateOrCreate(['name' => 'Auditorio Junín'], [
            'description' => 'Sala del auditorio Junín de la Cámara de Comercio de Huancayo.',
            'default_rate' => 130.00,
            'is_active' => true,
        ]);
    }

    public function down(): void
    {
        // No revertimos el renombrado/las tarifas — son datos reales del
        // cliente, no un cambio de esquema que tenga sentido deshacer.
    }
};
