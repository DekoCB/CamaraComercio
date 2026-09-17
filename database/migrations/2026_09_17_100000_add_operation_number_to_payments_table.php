<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // N° de operación bancaria/Yape/Plin que el asociado presenta
            // como comprobante — distinto del receipt_number de la factura,
            // que es el comprobante que la Cámara emite. Nullable: efectivo
            // no tiene número de operación.
            $table->string('operation_number', 60)->nullable()->after('method');
            $table->index('operation_number');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['operation_number']);
            $table->dropColumn('operation_number');
        });
    }
};
