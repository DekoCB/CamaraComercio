<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    public const STATUS_PENDIENTE = 'PENDIENTE';

    public const STATUS_PARCIAL = 'PARCIAL';

    public const STATUS_PAGADA = 'PAGADA';

    public const STATUS_VENCIDA = 'VENCIDA';

    protected $fillable = [
        'associate_id',
        'period',
        'amount',
        'paid_total',
        'issue_date',
        'due_date',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_total' => 'decimal:2',
            'issue_date' => 'date',
            'due_date' => 'date',
        ];
    }

    public function associate(): BelongsTo
    {
        return $this->belongsTo(Associate::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function balance(): float
    {
        return round((float) $this->amount - (float) $this->paid_total, 2);
    }

    /**
     * The stored `status` column only ever reflects the payment state
     * (PENDIENTE/PARCIAL/PAGADA) — it is updated transactionally by
     * PaymentService whenever a payment is registered. "Vencida" is
     * inherently a function of *today's* date, not of any write event,
     * so it is computed here on read rather than persisted: persisting
     * it would need a daily batch job and would risk a stale value
     * between runs. Reports/portfolio queries in Sprint 3 use
     * scopeOverdue() for the same computation at the query level.
     */
    public function effectiveStatus(): string
    {
        if ($this->status === self::STATUS_PAGADA) {
            return self::STATUS_PAGADA;
        }

        return $this->isOverdue() ? self::STATUS_VENCIDA : $this->status;
    }

    public function isOverdue(): bool
    {
        return $this->status !== self::STATUS_PAGADA && $this->due_date->isPast();
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_PAGADA)
            ->whereDate('due_date', '<', now()->toDateString());
    }

    public function scopeForPeriod(Builder $query, string $period): Builder
    {
        return $query->where('period', $period);
    }
}
