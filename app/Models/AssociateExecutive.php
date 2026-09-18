<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row of the Ficha de Inscripción's "Principales Ejecutivos" table.
 * Rows are fully replaced on every regeneration of the ficha
 * (AssociateInscriptionService) rather than diffed — there is no need
 * to track history for this list, only the current one.
 */
class AssociateExecutive extends Model
{
    use HasFactory;

    protected $fillable = [
        'associate_id',
        'name',
        'position',
        'phone',
        'birthday',
    ];

    protected function casts(): array
    {
        return [
            'birthday' => 'date',
        ];
    }

    public function associate(): BelongsTo
    {
        return $this->belongsTo(Associate::class);
    }
}
