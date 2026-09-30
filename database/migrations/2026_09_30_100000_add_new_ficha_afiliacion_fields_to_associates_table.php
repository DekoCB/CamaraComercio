<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La CCH renovó el formato físico de la Ficha de Afiliación — este
     * agrega los campos que el formato nuevo pide y el viejo no tenía.
     * "Fecha de inicio de Actividades" (activities_started_at) sigue
     * existiendo pero ya no se imprime en la ficha: ese lugar del
     * formulario ahora es "Fecha de Aniversario" (anniversary_date, que
     * ya existía para el calendario de cumpleaños).
     */
    public function up(): void
    {
        Schema::table('associates', function (Blueprint $table) {
            $table->string('internal_code', 30)->nullable()->after('id');
            $table->string('mobile_phone', 40)->nullable()->after('contact_phone');
            $table->string('fax', 40)->nullable()->after('mobile_phone');
            $table->string('address_number', 20)->nullable()->after('billing_address');
            $table->string('address_lot_interior', 60)->nullable()->after('address_number');
            $table->text('address_reference')->nullable()->after('address_lot_interior');
            $table->string('sector_economico', 150)->nullable()->after('ciiu');
            $table->text('main_inputs')->nullable()->after('sector_economico');
            $table->text('main_suppliers')->nullable()->after('main_inputs');
            $table->string('employee_count_range', 30)->nullable()->after('main_suppliers');
            $table->string('assets_range', 30)->nullable()->after('employee_count_range');
            $table->string('monthly_sales_range', 30)->nullable()->after('assets_range');
            $table->string('annual_sales_range', 30)->nullable()->after('monthly_sales_range');
            $table->json('trade_associations')->nullable()->after('annual_sales_range');
            $table->text('interested_services')->nullable()->after('trade_associations');
            $table->decimal('registration_fee', 10, 2)->nullable()->after('monthly_fee');
            $table->decimal('annual_fee', 10, 2)->nullable()->after('registration_fee');
            $table->string('registration_payment_method', 20)->nullable()->after('annual_fee');
        });
    }

    public function down(): void
    {
        Schema::table('associates', function (Blueprint $table) {
            $table->dropColumn([
                'internal_code',
                'mobile_phone',
                'fax',
                'address_number',
                'address_lot_interior',
                'address_reference',
                'sector_economico',
                'main_inputs',
                'main_suppliers',
                'employee_count_range',
                'assets_range',
                'monthly_sales_range',
                'annual_sales_range',
                'trade_associations',
                'interested_services',
                'registration_fee',
                'annual_fee',
                'registration_payment_method',
            ]);
        });
    }
};
