<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ítems de un requerimiento — el documento real no siempre calza
     * cantidad × precio unitario con el importe final (p.ej. precios sin
     * IGV vs. importe final con IGV), así que el importe se captura
     * directo en vez de derivarlo; cantidad/precio quedan opcionales,
     * solo de referencia.
     */
    public function up(): void
    {
        Schema::create('payment_requisition_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_requisition_id')->constrained()->cascadeOnDelete();
            $table->date('item_date')->nullable();
            $table->string('reference', 40)->nullable();
            $table->string('description');
            $table->decimal('quantity', 8, 2)->nullable();
            $table->decimal('unit_price', 8, 2)->nullable();
            $table->decimal('amount', 8, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_requisition_items');
    }
};
