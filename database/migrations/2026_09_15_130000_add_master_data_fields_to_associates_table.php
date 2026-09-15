<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Brings the associates table in line with the Cámara's master Excel
 * ("DATA DE ASOCIADOS", columns C–AJ + OBSERVACIONES). Existing columns
 * keep their names but change meaning slightly: `name` is now the
 * "Razón social", `company` the "Nombre comercial" and `email` the
 * company email. Everything new is nullable so current rows stay valid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('associates', function (Blueprint $table) {
            // ESTADO / SECTORISTA / CAT. / MONTO A PAGAR
            $table->string('status', 20)->default('ACTIVO')->after('name');
            $table->string('sectorista', 100)->nullable()->after('status');
            $table->string('category', 10)->nullable()->after('sectorista');
            $table->decimal('monthly_fee', 10, 2)->nullable()->after('category');

            // FECHA DE INGRESO / TIPO DE PERSONA / FECHA DE ANIVERSARIO
            $table->date('joined_at')->nullable()->after('monthly_fee');
            $table->string('person_type', 30)->nullable()->after('joined_at');
            $table->date('anniversary_date')->nullable()->after('person_type');

            // Direcciones
            $table->string('billing_address', 255)->nullable()->after('email');
            $table->string('billing_district', 100)->nullable()->after('billing_address');
            $table->string('mailing_address', 255)->nullable()->after('billing_district');
            $table->string('mailing_district', 100)->nullable()->after('mailing_address');

            // Clasificación
            $table->string('company_size', 50)->nullable()->after('mailing_district');
            $table->string('activity_type', 50)->nullable()->after('company_size');
            $table->string('sector_committee', 150)->nullable()->after('activity_type');
            $table->string('ciiu', 255)->nullable()->after('sector_committee');
            $table->text('sub_sector')->nullable()->after('ciiu');

            // Representante legal
            $table->string('legal_rep_name', 150)->nullable()->after('sub_sector');
            $table->string('legal_rep_dni', 20)->nullable()->after('legal_rep_name');
            $table->string('legal_rep_gender', 20)->nullable()->after('legal_rep_dni');
            $table->date('legal_rep_birthday')->nullable()->after('legal_rep_gender');
            $table->string('legal_rep_phone', 40)->nullable()->after('legal_rep_birthday');
            $table->string('legal_rep_email', 190)->nullable()->after('legal_rep_phone');

            // Representante ante la CCH
            $table->string('cch_rep_name', 150)->nullable()->after('legal_rep_email');
            $table->string('cch_rep_dni', 20)->nullable()->after('cch_rep_name');
            $table->string('cch_rep_gender', 20)->nullable()->after('cch_rep_dni');
            $table->date('cch_rep_birthday')->nullable()->after('cch_rep_gender');
            $table->string('cch_rep_phone', 40)->nullable()->after('cch_rep_birthday');
            $table->string('cch_rep_email', 190)->nullable()->after('cch_rep_phone');

            // IMAGENES (logo/foto) y OBSERVACIONES
            $table->string('image_path', 255)->nullable()->after('cch_rep_email');
            $table->text('notes')->nullable()->after('image_path');

            $table->index('status');
            $table->index('sectorista');
        });

        // Keep the new status coherent with the flag rows already carry.
        DB::table('associates')->where('is_active', false)->update(['status' => 'SUSPENDIDO']);
    }

    public function down(): void
    {
        Schema::table('associates', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['sectorista']);
            $table->dropColumn([
                'status', 'sectorista', 'category', 'monthly_fee',
                'joined_at', 'person_type', 'anniversary_date',
                'billing_address', 'billing_district', 'mailing_address', 'mailing_district',
                'company_size', 'activity_type', 'sector_committee', 'ciiu', 'sub_sector',
                'legal_rep_name', 'legal_rep_dni', 'legal_rep_gender', 'legal_rep_birthday', 'legal_rep_phone', 'legal_rep_email',
                'cch_rep_name', 'cch_rep_dni', 'cch_rep_gender', 'cch_rep_birthday', 'cch_rep_phone', 'cch_rep_email',
                'image_path', 'notes',
            ]);
        });
    }
};
