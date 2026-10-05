<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * chairs/tables/projector (Partida 042) quedan reemplazados por el
     * catálogo real de equipos (rental_catalog_items/rental_line_items) —
     * la cotización real del cliente trajo ~15 ítems, no 3 campos fijos.
     * bank_account se conserva: sigue siendo útil como anulación puntual
     * sobre la cuenta oficial por defecto.
     */
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropColumn(['chairs', 'tables', 'projector']);
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->unsignedSmallInteger('chairs')->nullable();
            $table->unsignedSmallInteger('tables')->nullable();
            $table->boolean('projector')->default(false);
        });
    }
};
