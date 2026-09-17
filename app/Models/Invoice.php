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

    /**
     * Like VENCIDA, never stored in the `status` column — computed from
     * voided_at, the same way Payment tracks a void without touching its
     * own status-like fields. Takes priority over every other status.
     */
    public const STATUS_ANULADA = 'ANULADA';

    /**
     * The single source of truth for the balance formula, expressed as a
     * raw SQL fragment. balance() below is the same formula for a single
     * loaded record; this exists because SUM() aggregates in ReportService
     * and DashboardService need the formula in SQL, where a PHP method
     * can't be called from inside SUM(amount - paid_total). Keeping both
     * spellings next to each other (and this one named, not copy-pasted)
     * is what "centralizar la lógica de negocio" means when the same rule
     * genuinely has to exist in two languages.
     */
    public const BALANCE_SQL = 'amount - paid_total';

    protected $fillable = [
        'associate_id',
        'period',
        'receipt_number',
        'amount',
        'paid_total',
        'issue_date',
        'due_date',
        'status',
        'created_by',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_total' => 'decimal:2',
            'issue_date' => 'date',
            'due_date' => 'date',
            'voided_at' => 'datetime',
        ];
    }

    public function associate(): BelongsTo
    {
        return $this->belongsTo(Associate::class);
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Payments that still count — voided ones stay in the table for the
     * audit trail but are excluded from paid_total and from what the
     * Pagos list shows as "fecha de pago".
     */
    public function activePayments(): HasMany
    {
        return $this->hasMany(Payment::class)->whereNull('voided_at')->orderByDesc('paid_at');
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
        if ($this->isVoided()) {
            return self::STATUS_ANULADA;
        }

        if ($this->status === self::STATUS_PAGADA) {
            return self::STATUS_PAGADA;
        }

        return $this->isOverdue() ? self::STATUS_VENCIDA : $this->status;
    }

    public function isOverdue(): bool
    {
        return ! $this->isVoided() && $this->status !== self::STATUS_PAGADA && $this->due_date->isPast();
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNull('voided_at')
            ->where('status', '!=', self::STATUS_PAGADA)
            ->whereDate('due_date', '<', now()->toDateString());
    }

    public function scopeForPeriod(Builder $query, string $period): Builder
    {
        return $query->where('period', $period);
    }

    public function scopeVoided(Builder $query): Builder
    {
        return $query->whereNotNull('voided_at');
    }

    /**
     * "No pagadas" in the Pagos module: anything with a balance, whether
     * nothing or only part of it has been collected (PENDIENTE + PARCIAL,
     * overdue or not) — anuladas no longer owe anything, so they're
     * excluded just like pagadas.
     */
    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->whereNull('voided_at')->where('status', '!=', self::STATUS_PAGADA);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PAGADA);
    }
}
