<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves docs/OPEN_BUSINESS_DECISIONS.md pregunta 12 (identificador único
 * del asociado): se adopta el RUC (Option B) como identificador legal
 * opcional. Se mantiene nullable — no se hace obligatorio porque eso
 * bloquearía altas legítimas donde el RUC no está disponible al momento
 * del registro, y volverlo obligatorio no fue parte de lo autorizado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('associates', function (Blueprint $table) {
            $table->string('ruc', 11)->nullable()->unique()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('associates', function (Blueprint $table) {
            $table->dropUnique(['ruc']);
            $table->dropColumn('ruc');
        });
    }
};
