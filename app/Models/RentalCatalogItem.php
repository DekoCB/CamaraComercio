<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * "BIENES DE CCH" — catálogo de equipos/servicios que se ofrecen junto con
 * el alquiler de un espacio (ver la migración de creación). Mismo patrón
 * que Space/Benefit: catálogo chico administrable desde pantalla.
 */
class RentalCatalogItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'default_hourly_rate',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'default_hourly_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(RentalLineItem::class, 'catalog_item_id');
    }
}
