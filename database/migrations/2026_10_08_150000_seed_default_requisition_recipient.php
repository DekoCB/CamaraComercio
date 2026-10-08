<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Destinatario real de los 2 requerimientos de ejemplo que trajo el
     * usuario — prellenado editable, mismo patrón que la cuenta bancaria
     * oficial de Alquileres.
     */
    public function up(): void
    {
        Setting::set('rentals.requisitions.recipient_name', Setting::get('rentals.requisitions.recipient_name', 'Klaus Castro Pimentel'));
        Setting::set('rentals.requisitions.recipient_role', Setting::get('rentals.requisitions.recipient_role', 'Gerente General de Cámara de Comercio de Huancayo'));
    }

    public function down(): void
    {
        Setting::where('key', 'rentals.requisitions.recipient_name')->delete();
        Setting::where('key', 'rentals.requisitions.recipient_role')->delete();
    }
};
