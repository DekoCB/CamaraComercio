<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Partida 042 lo dejó en varchar(100) pensando en una línea de texto
     * corta; la cuenta oficial real es un bloque de varias líneas (banco,
     * N° de cuenta, CCI, titulares) que ya lo supera.
     */
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->text('bank_account')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->string('bank_account', 100)->nullable()->change();
        });
    }
};
