<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Control de estacionamiento del propio local (oct-2026) — alcance
     * acordado con el usuario: placa + asociado dueño (si aplica), entrada
     * y salida (bitácora diaria), y un cobro opcional por sesión. Solo
     * Administrador lo usa, así que un único permiso (parking.manage)
     * alcanza, sin separar ver/gestionar como en Protestos.
     */
    public function up(): void
    {
        Schema::create('parking_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('plate', 10);
            // No todo vehículo que entra es de un asociado — mismo criterio
            // que Protest::associate_id (nullable, con un nombre libre como
            // respaldo cuando no lo es).
            $table->foreignId('associate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('owner_name')->nullable();
            $table->string('vehicle_description')->nullable();
            $table->dateTime('entered_at');
            // Null = todavía está estacionado — no hay un estado aparte,
            // igual que Rental deriva "vencida" sin guardarlo.
            $table->dateTime('exited_at')->nullable();
            $table->decimal('amount', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('plate');
            $table->index('entered_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parking_sessions');
    }
};
