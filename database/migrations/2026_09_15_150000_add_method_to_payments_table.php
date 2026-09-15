<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // How the associate paid (EFECTIVO, TRANSFERENCIA, YAPE…); see
            // Payment::METHODS. Nullable so payments registered before this
            // column existed stay valid — they show as "Sin especificar".
            $table->string('method', 30)->nullable()->after('paid_at');
            $table->index('method');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['method']);
            $table->dropColumn('method');
        });
    }
};
