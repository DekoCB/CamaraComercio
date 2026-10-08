<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Requerimientos de pago y reembolsos de Logística (oct-2026) — nueva
     * pestaña dentro de Alquileres, a pedido explícito, aunque no tiene
     * relación con el alquiler de espacios en sí. Numeración propia
     * ("000063-2026") autoincremental por año — sequence+year en vez de
     * guardar el string ya formateado, para poder calcular el siguiente
     * número de forma segura.
     */
    public function up(): void
    {
        Schema::create('payment_requisitions', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('sequence');
            $table->string('requester_area')->nullable();
            $table->string('recipient_name');
            $table->string('recipient_role')->nullable();
            $table->string('subject');
            $table->date('issued_at');
            $table->string('beneficiary_name');
            $table->text('bank_details')->nullable();
            $table->string('provider_ruc', 20)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['year', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_requisitions');
    }
};
