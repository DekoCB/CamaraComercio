<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo de espacios alquilables (auditorio, salas de reunión, etc.) —
 * mismo patrón que Benefit: una tabla chica administrable desde la propia
 * pantalla, en vez de un campo fijo por espacio.
 */
class Space extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'default_rate',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }
}
