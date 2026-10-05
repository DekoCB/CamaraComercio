<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            // Alcance simple a propósito (oct-2026): el cliente va a mandar
            // una lista más completa de mobiliario/equipos más adelante —
            // por ahora solo lo que pidió explícitamente. "Horas" no se
            // guarda aparte: se calcula de starts_at/ends_at para que nunca
            // pueda quedar en desacuerdo con el horario real.
            $table->unsignedSmallInteger('chairs')->nullable()->after('amount');
            $table->unsignedSmallInteger('tables')->nullable()->after('chairs');
            $table->boolean('projector')->default(false)->after('tables');
            $table->string('bank_account', 100)->nullable()->after('projector');
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropColumn(['chairs', 'tables', 'projector', 'bank_account']);
        });
    }
};
