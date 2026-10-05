<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Cuenta oficial real tomada de la cotización que trajo el cliente
     * ("COTIZACION AUD. MENOR - BRANKO PERU.pdf", oct-2026) — queda como
     * valor por defecto editable desde "Equipos y cuenta bancaria".
     */
    public function up(): void
    {
        Setting::set('rentals.bank_account_official', implode("\n", [
            'CUENTA OFICIAL BBVA:',
            'CUENTA BBVA: 0011-0235-02019704-13',
            'CCI: 011-235-000201970413-95',
            'A NOMBRE: Fanny Galván Muñico y José Luis García Terrazos',
        ]));
    }

    public function down(): void
    {
        Setting::where('key', 'rentals.bank_account_official')->delete();
    }
};
