<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Estacionamiento" (oct-2026) resultó ser un malentendido — el
     * sistema real es de emisión de placas vehiculares (ver
     * plate_issuances), no una bitácora de entrada/salida de un
     * estacionamiento. Se retira en una migración aparte, no editando la
     * que la creó, porque esa ya corrió en producción (lote 8).
     */
    public function up(): void
    {
        Schema::dropIfExists('parking_sessions');
    }

    public function down(): void
    {
        // Intencionalmente sin recrear la tabla — el módulo que la usaba
        // ya no existe en el código.
    }
};
