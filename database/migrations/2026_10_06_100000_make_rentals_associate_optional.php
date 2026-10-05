<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La cotización real que trajo el cliente (oct-2026) iba dirigida a
     * "BRANKO PERÚ" — no todo alquiler es de un asociado, a pesar de lo
     * asumido en la Partida 026. Se agrega un nombre libre como respaldo,
     * mismo criterio que Protest::creditor_name/ParkingSession::owner_name.
     */
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->foreignId('associate_id')->nullable()->change();
            $table->string('client_name')->nullable()->after('associate_id');
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropColumn('client_name');
            $table->foreignId('associate_id')->nullable(false)->change();
        });
    }
};
