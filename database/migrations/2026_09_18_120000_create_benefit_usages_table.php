<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('benefit_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('associate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('benefit_id')->constrained()->restrictOnDelete();
            $table->date('used_at');
            $table->string('notes', 255)->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['associate_id', 'benefit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benefit_usages');
    }
};
