<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Coffee Break" — cotización de catering aparte del alquiler del
 * espacio, tomada de una cotización real del cliente. Las 3 listas son
 * cerradas porque así está en ese documento ("Elegir 1 opción" cada una).
 */
class RentalCatering extends Model
{
    use HasFactory;

    public const DRINK_OPTIONS = [
        'Café', 'Infusiones', 'Jugo caliente de frutas o emoliente', 'Gaseosa 500ml',
    ];

    public const SANDWICH_OPTIONS = [
        'Sandwich de pollo con apio y pecanas', 'Sandwich de pollo con durazno y pecanas',
        'Sandwich de asado', 'Sandwich de jamón de casa', 'Empanada de carne', 'Empanada de pollo',
    ];

    public const DESSERT_OPTIONS = [
        'Keke inglés glaseado', 'Tartaletas de piña', 'Tartaletas de manzana',
        'Pionono', 'Alfajorcito', 'Rollito de manjar',
    ];

    protected $fillable = [
        'rental_id',
        'people_count',
        'drink_option',
        'sandwich_option',
        'dessert_option',
        'daily_cost',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'daily_cost' => 'decimal:2',
        ];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }
}
