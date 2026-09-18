<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('associates', function (Blueprint $table) {
            // Campos de la "Ficha de Afiliación" física que todavía no
            // tenían columna propia — el resto de la ficha (razón social,
            // RUC, representantes, CIIU...) ya existe desde antes.
            $table->string('legal_rep_position', 100)->nullable()->after('legal_rep_email');
            $table->string('cch_rep_position', 100)->nullable()->after('cch_rep_email');
            $table->date('activities_started_at')->nullable()->after('joined_at');
            $table->string('billing_province', 100)->nullable()->after('billing_district');
            $table->string('website', 190)->nullable()->after('email');
            $table->string('profession', 100)->nullable()->after('sub_sector');
            $table->string('public_registry_entry', 100)->nullable()->after('profession');
            $table->string('public_registry_title', 100)->nullable()->after('public_registry_entry');
            // "Actividad Principal (marque solo una)" / "Actividades
            // Complementarias (puede marcar más de una)" — mismo catálogo
            // de 6 opciones para ambas, ver Associate::ACTIVITY_OPTIONS.
            $table->string('main_activity', 30)->nullable()->after('public_registry_title');
            $table->json('complementary_activities')->nullable()->after('main_activity');
        });
    }

    public function down(): void
    {
        Schema::table('associates', function (Blueprint $table) {
            $table->dropColumn([
                'legal_rep_position',
                'cch_rep_position',
                'activities_started_at',
                'billing_province',
                'website',
                'profession',
                'public_registry_entry',
                'public_registry_title',
                'main_activity',
                'complementary_activities',
            ]);
        });
    }
};
