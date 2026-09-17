<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Beneficios de los asociados (acta2.txt [~15:30]): "usos gratuitos
 * anuales de auditorios" was the only concrete example given, but the
 * client framed it generally ("control de beneficios"), so this is a
 * small catalog rather than one hardcoded auditorium field — a second
 * benefit later is a new row, not a migration.
 */
class Benefit extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'annual_quota',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'annual_quota' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function usages(): HasMany
    {
        return $this->hasMany(BenefitUsage::class);
    }
}
