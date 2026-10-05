<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('associates', function (Blueprint $table) {
            // Null = aún no juramentado. La afiliación (joined_at) y la
            // juramentación son dos eventos distintos — un asociado puede
            // estar registrado y activo por un tiempo antes de juramentarse.
            $table->date('sworn_in_at')->nullable()->after('joined_at');
        });
    }

    public function down(): void
    {
        Schema::table('associates', function (Blueprint $table) {
            $table->dropColumn('sworn_in_at');
        });
    }
};
