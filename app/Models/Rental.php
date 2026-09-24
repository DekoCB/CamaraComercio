<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Alquiler de un espacio a un asociado. Una sola fila cubre todo el ciclo
 * — COTIZADA (aún sin confirmar, solo un monto propuesto) → CONFIRMADA
 * (la reserva ya bloquea el espacio) → FACTURADA (ya cobrada) — en vez de
 * una tabla de cotizaciones separada de la de reservas: son el mismo
 * registro en distintos momentos, igual que Invoice nunca separa "factura
 * en borrador" de "factura emitida". CANCELADA es el único borrado que
 * existe, nunca uno físico (mismo criterio que Invoice/Payment).
 */
class Rental extends Model
{
    use HasFactory;

    public const STATUS_COTIZADA = 'COTIZADA';

    public const STATUS_CONFIRMADA = 'CONFIRMADA';

    public const STATUS_FACTURADA = 'FACTURADA';

    public const STATUS_CANCELADA = 'CANCELADA';

    public const STATUSES = [self::STATUS_COTIZADA, self::STATUS_CONFIRMADA, self::STATUS_FACTURADA, self::STATUS_CANCELADA];

    public const STATUS_LABELS = [
        self::STATUS_COTIZADA => 'Cotizada',
        self::STATUS_CONFIRMADA => 'Confirmada',
        self::STATUS_FACTURADA => 'Facturada',
        self::STATUS_CANCELADA => 'Cancelada',
    ];

    protected $fillable = [
        'space_id',
        'associate_id',
        'starts_at',
        'ends_at',
        'purpose',
        'amount',
        'status',
        'notes',
        'created_by',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'amount' => 'decimal:2',
            'cancelled_at' => 'datetime',
        ];
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function associate(): BelongsTo
    {
        return $this->belongsTo(Associate::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELADA;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /** Bookings that still hold the space — a quote alone doesn't. */
    public function scopeBlocksSpace(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_CONFIRMADA, self::STATUS_FACTURADA]);
    }

    /**
     * Other bookings for the same space whose time range overlaps
     * [$start, $end) — the standard "A starts before B ends, and B starts
     * before A ends" interval-overlap test. Used to block double-booking
     * a space once a rental is confirmed.
     */
    public function scopeOverlapping(Builder $query, int $spaceId, \DateTimeInterface $start, \DateTimeInterface $end, ?int $ignoreId = null): Builder
    {
        return $query
            ->where('space_id', $spaceId)
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->when($ignoreId, fn (Builder $q) => $q->where('id', '!=', $ignoreId));
    }
}
