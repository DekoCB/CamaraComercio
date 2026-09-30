<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('associate_executives', function (Blueprint $table) {
            $table->string('email', 190)->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('associate_executives', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};
