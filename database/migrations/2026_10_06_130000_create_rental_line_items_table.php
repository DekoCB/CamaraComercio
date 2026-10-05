<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();
            // Nullable: un ítem fuera del catálogo (algo que el cliente
            // pidió puntualmente) se guarda solo con description.
            $table->foreignId('catalog_item_id')->nullable()->constrained('rental_catalog_items')->nullOnDelete();
            $table->string('description')->nullable();
            $table->decimal('quantity', 8, 2)->default(0);
            $table->decimal('hourly_rate', 8, 2)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_line_items');
    }
};
