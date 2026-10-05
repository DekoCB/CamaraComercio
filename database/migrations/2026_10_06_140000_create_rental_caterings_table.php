<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Coffee Break" — cotización aparte del alquiler del espacio en sí,
     * una fila opcional por alquiler (no todos lo piden). Mismo patrón de
     * la cotización real: un menú de 3 categorías a elegir 1 opción cada
     * una, para N personas, a un costo plano por día.
     */
    public function up(): void
    {
        Schema::create('rental_caterings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('people_count')->nullable();
            $table->string('drink_option')->nullable();
            $table->string('sandwich_option')->nullable();
            $table->string('dessert_option')->nullable();
            $table->decimal('daily_cost', 8, 2)->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_caterings');
    }
};
