<?php

use App\Models\RentalCatalogItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "BIENES DE CCH" — el catálogo real de equipos/servicios que ofrece
     * la Cámara junto con el auditorio, tomado tal cual de una cotización
     * real del cliente ("COTIZACION AUD. MENOR - BRANKO PERU.pdf", oct-2026).
     * Casi todos se incluyen sin costo aparte (tarifa vacía) — solo el
     * proyector y la vigilancia de fin de semana tienen tarifa propia en
     * el documento real.
     */
    public function up(): void
    {
        Schema::create('rental_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('default_hourly_rate', 8, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $items = [
            ['Proyector multimedia - ecrán', 30.00],
            ['Laptop o televisor (web cam)', null],
            ['Uso de beneficio (asociado)', null],
            ['Sillas plásticas con fundas (60)', null],
            ['Consola', null],
            ['Mesas rectangulares con mantel', null],
            ['Sillas plásticas sin fundas', null],
            ['Micrófonos inalámbricos (2)', null],
            ['Pizarra acrílica', null],
            ['Cable de extensión', null],
            ['Cable de audio', null],
            ['Equipo de sonido', null],
            ['Pilas cargables', null],
            ['Pilas no cargables', null],
            ['Vigilancia sábado o domingo', 50.00],
        ];

        foreach ($items as $i => [$name, $rate]) {
            RentalCatalogItem::updateOrCreate(['name' => $name], [
                'default_hourly_rate' => $rate,
                'is_active' => true,
                'sort_order' => $i + 1,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_catalog_items');
    }
};
