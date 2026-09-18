<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row of the Ficha de Inscripción's "Productos y Servicios" table —
 * same full-replace-on-regenerate approach as AssociateExecutive.
 */
class AssociateProduct extends Model
{
    use HasFactory;

    /**
     * Maps each checkbox column of the ficha to its boolean column, in
     * the same F/P/C/I/S/E order the physical form uses.
     */
    public const FLAGS = [
        'is_fabrica' => 'F',
        'is_produce' => 'P',
        'is_comercializa' => 'C',
        'is_importa' => 'I',
        'is_servicios' => 'S',
        'is_exporta' => 'E',
    ];

    protected $fillable = [
        'associate_id',
        'description',
        'is_fabrica',
        'is_produce',
        'is_comercializa',
        'is_importa',
        'is_servicios',
        'is_exporta',
    ];

    protected function casts(): array
    {
        return [
            'is_fabrica' => 'boolean',
            'is_produce' => 'boolean',
            'is_comercializa' => 'boolean',
            'is_importa' => 'boolean',
            'is_servicios' => 'boolean',
            'is_exporta' => 'boolean',
        ];
    }

    public function associate(): BelongsTo
    {
        return $this->belongsTo(Associate::class);
    }
}
