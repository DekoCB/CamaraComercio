<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Requerimiento de pago o reembolso de Logística (oct-2026) — documento
 * formal numerado ("000063-2026 LGO/CCH") dirigido a Gerencia. Ya emitido
 * y firmado en papel cuando se registra aquí, así que — mismo criterio
 * que Protest/PlateIssuance — nunca se edita ni se borra, solo se
 * consulta y se vuelve a descargar.
 */
class PaymentRequisition extends Model
{
    use HasFactory;

    public const TYPE_REEMBOLSO = 'REEMBOLSO';

    public const TYPE_PAGO_PROVEEDOR = 'PAGO_PROVEEDOR';

    public const TYPES = [
        self::TYPE_REEMBOLSO => 'Reembolso',
        self::TYPE_PAGO_PROVEEDOR => 'Pago a proveedor',
    ];

    protected $fillable = [
        'type',
        'year',
        'sequence',
        'requester_area',
        'recipient_name',
        'recipient_role',
        'subject',
        'issued_at',
        'beneficiary_name',
        'bank_details',
        'provider_ruc',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PaymentRequisitionItem::class)->orderBy('sort_order');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /** "REQUERIMIENTO N° 000063-2026 LGO/CCH" del documento real. */
    public function documentNumber(): string
    {
        return sprintf('%06d-%d LGO/CCH', $this->sequence, $this->year);
    }

    public function total(): float
    {
        return round((float) $this->items->sum('amount'), 2);
    }
}
