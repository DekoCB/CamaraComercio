<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Valores sueltos editables desde pantalla que no son lo bastante
     * grandes para su propia tabla — hoy solo la tarifa por hora del
     * proyector (oct-2026), pero el mismo patrón sirve para la próxima
     * tarifa de equipo que llegue sin necesitar otra migración.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('value')->nullable();
            $table->timestamps();
        });

        Setting::set('rentals.projector_hourly_rate', '30.00');
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
