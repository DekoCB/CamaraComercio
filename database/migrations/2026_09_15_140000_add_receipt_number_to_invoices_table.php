<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Cámara's master workbook records, under each month column, the
     * number of the comprobante (F020-00001289, FE01-000322, …) issued
     * for that month's contribution. Kept on the invoice — one period,
     * one comprobante — so the Pagos module can show and search it.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('receipt_number', 60)->nullable()->after('period')
                ->comment('N° de comprobante (Excel "AÑO XXXX" grid)');
            $table->index('receipt_number');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['receipt_number']);
            $table->dropColumn('receipt_number');
        });
    }
};
