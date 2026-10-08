<?php

use App\Models\PlateIssuance;
use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Una clave de tarifa por tipo de trámite, igual patrón que
     * rentals.projector_hourly_rate — sin precios reales todavía (el
     * cliente no los ha dado), así que quedan vacías hasta que se
     * completen desde la pantalla de Placas.
     */
    public function up(): void
    {
        foreach (array_keys(PlateIssuance::PROCEDURE_TYPES) as $type) {
            $key = PlateIssuance::rateSettingKey($type);

            if (Setting::get($key) === null) {
                Setting::set($key, null);
            }
        }
    }

    public function down(): void
    {
        foreach (array_keys(PlateIssuance::PROCEDURE_TYPES) as $type) {
            Setting::where('key', PlateIssuance::rateSettingKey($type))->delete();
        }
    }
};
