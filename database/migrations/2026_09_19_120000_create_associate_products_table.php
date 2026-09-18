<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('associate_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('associate_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            // F / P / C / I / S / E de la ficha: Fabrica, Produce,
            // Comercializa, Importa, Servicios, Exporta — una fila puede
            // marcar más de una columna.
            $table->boolean('is_fabrica')->default(false);
            $table->boolean('is_produce')->default(false);
            $table->boolean('is_comercializa')->default(false);
            $table->boolean('is_importa')->default(false);
            $table->boolean('is_servicios')->default(false);
            $table->boolean('is_exporta')->default(false);
            $table->timestamps();

            $table->index('associate_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('associate_products');
    }
};
