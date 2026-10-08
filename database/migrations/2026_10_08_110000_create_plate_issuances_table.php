<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Emisión de placas vehiculares (oct-2026) — nueva placa, duplicado,
     * tercera placa, boleta informativa, u otro trámite, cada uno con su
     * costo cobrado por factura o boleta. Un solo permiso
     * (plates.manage) alcanza: solo Administrador y Gestión de Asociados
     * lo usan, sin un rol que solo vea.
     */
    public function up(): void
    {
        Schema::create('plate_issuances', function (Blueprint $table) {
            $table->id();
            $table->string('procedure_type', 30);
            // Solo tiene sentido cuando procedure_type es OTROS.
            $table->string('other_description')->nullable();
            $table->string('plate_number', 10)->nullable();
            // El solicitante puede no ser asociado — mismo criterio que
            // Rental::associate_id / client_name.
            $table->foreignId('associate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('client_name')->nullable();
            $table->string('vehicle_description')->nullable();
            $table->string('receipt_type', 10);
            $table->string('receipt_number', 30)->nullable();
            $table->decimal('amount', 8, 2);
            $table->date('issued_at');
            $table->text('notes')->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('procedure_type');
            $table->index('issued_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plate_issuances');
    }
};
