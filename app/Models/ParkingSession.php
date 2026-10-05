<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una visita de un vehículo al estacionamiento del propio local (oct-2026)
 * — una sola fila cubre entrada y salida (exited_at null = sigue
 * estacionado), mismo criterio que Rental: nunca borrado físico, se
 * corrige editando mientras sigue abierta.
 */
class ParkingSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'plate',
        'associate_id',
        'owner_name',
        'vehicle_description',
        'entered_at',
        'exited_at',
        'amount',
        'notes',
        'registered_by',
    ];

    protected function casts(): array
    {
        return [
            'entered_at' => 'datetime',
            'exited_at' => 'datetime',
            'amount' => 'decimal:2',
        ];
    }

    public function associate(): BelongsTo
    {
        return $this->belongsTo(Associate::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function isParked(): bool
    {
        return $this->exited_at === null;
    }

    public function ownerLabel(): string
    {
        return $this->associate->name ?? $this->owner_name ?? '-';
    }
}
