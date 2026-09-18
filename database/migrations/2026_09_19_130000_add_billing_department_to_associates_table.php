<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('associates', function (Blueprint $table) {
            // "Departamento" de la Declaración Jurada — un nivel más que
            // provincia/distrito, que ya existían; ningún otro documento
            // lo pedía hasta ahora.
            $table->string('billing_department', 100)->nullable()->after('billing_province');
        });
    }

    public function down(): void
    {
        Schema::table('associates', function (Blueprint $table) {
            $table->dropColumn('billing_department');
        });
    }
};
