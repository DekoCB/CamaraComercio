<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    /**
     * Payment channels the Cámara receives money through. Keys are what
     * gets stored; labels are what the UI shows.
     */
    public const METHODS = [
        'EFECTIVO' => 'Efectivo',
        'TRANSFERENCIA' => 'Transferencia bancaria',
        'DEPOSITO' => 'Depósito bancario',
        'YAPE' => 'Yape',
        'PLIN' => 'Plin',
        'TARJETA' => 'Tarjeta',
        'CHEQUE' => 'Cheque',
        'OTRO' => 'Otro',
    ];

    protected $fillable = [
        'invoice_id',
        'amount',
        'paid_at',
        'method',
        'operation_number',
        'registered_by',
        'notes',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->method] ?? ($this->method ?: 'Sin especificar');
    }

    public function scopeActive($query)
    {
        // Qualified: invoices also has a voided_at column since factura
        // anulación shipped, and this scope is routinely used after a
        // join to invoices (e.g. ReportService::collections()), where an
        // unqualified column would be ambiguous.
        return $query->whereNull('payments.voided_at');
    }

    public function scopeVoided($query)
    {
        return $query->whereNotNull('payments.voided_at');
    }
}
