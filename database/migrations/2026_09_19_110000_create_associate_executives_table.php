<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('associate_executives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('associate_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('position', 100)->nullable();
            $table->string('phone', 40)->nullable();
            $table->date('birthday')->nullable();
            $table->timestamps();

            $table->index('associate_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('associate_executives');
    }
};
