<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de Protestos y Moras (sept-2026): servicio que la Ley de
 * Títulos Valores (Ley 27287, arts. 85-89) delega en las Cámaras de
 * Comercio y que la CCH ya cobra hoy en papel. Alcance provisional,
 * acordado explícitamente con el usuario mientras se confirma con el
 * cliente (ver la bitácora): registro propio de la CCH — sin integrarse
 * con el sistema nacional de la Cámara de Comercio de Lima —, solo los
 * campos indispensables, y sin la pantalla de regularización completa
 * todavía (solo el campo que la deja lista para más adelante).
 */
class Protest extends Model
{
    use HasFactory;

    public const TYPE_PROTESTO = 'PROTESTO';

    public const TYPE_MORA = 'MORA';

    public const TYPES = [
        self::TYPE_PROTESTO => 'Protesto',
        self::TYPE_MORA => 'Mora',
    ];

    /** Las 3 vías para formalizar un protesto — no son 3 servicios distintos. */
    public const CHANNEL_NOTARIAL = 'NOTARIAL';

    public const CHANNEL_JUDICIAL = 'JUDICIAL';

    public const CHANNEL_BANCARIO = 'BANCARIO';

    public const CHANNELS = [
        self::CHANNEL_NOTARIAL => 'Notarial',
        self::CHANNEL_JUDICIAL => 'Judicial',
        self::CHANNEL_BANCARIO => 'Bancario',
    ];

    public const INSTRUMENT_LETRA_CAMBIO = 'LETRA_DE_CAMBIO';

    public const INSTRUMENT_PAGARE = 'PAGARE';

    public const INSTRUMENT_FACTURA_NEGOCIABLE = 'FACTURA_NEGOCIABLE';

    public const INSTRUMENT_CHEQUE = 'CHEQUE';

    public const INSTRUMENT_OTRO = 'OTRO';

    public const INSTRUMENT_TYPES = [
        self::INSTRUMENT_LETRA_CAMBIO => 'Letra de cambio',
        self::INSTRUMENT_PAGARE => 'Pagaré',
        self::INSTRUMENT_FACTURA_NEGOCIABLE => 'Factura negociable',
        self::INSTRUMENT_CHEQUE => 'Cheque',
        self::INSTRUMENT_OTRO => 'Otro',
    ];

    public const STATUS_REGISTRADO = 'REGISTRADO';

    /**
     * Se comprobó que el título ya fue pagado, así que el registro queda
     * anulado — nunca se borra físicamente, igual que el resto del
     * sistema (Invoice, Payment, Rental).
     */
    public const STATUS_REGULARIZADO = 'REGULARIZADO';

    public const STATUS_LABELS = [
        self::STATUS_REGISTRADO => 'Registrado',
        self::STATUS_REGULARIZADO => 'Regularizado',
    ];

    protected $fillable = [
        'type',
        'channel',
        'instrument_type',
        'debtor_name',
        'debtor_document',
        'creditor_name',
        'creditor_document',
        'associate_id',
        'amount',
        'registered_at',
        'status',
        'regularized_at',
        'regularized_by',
        'regularization_notes',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'registered_at' => 'date',
            'regularized_at' => 'datetime',
        ];
    }

    public function associate(): BelongsTo
    {
        return $this->belongsTo(Associate::class);
    }

    public function regularizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'regularized_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isRegularized(): bool
    {
        return $this->status === self::STATUS_REGULARIZADO;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function channelLabel(): string
    {
        return self::CHANNELS[$this->channel] ?? $this->channel;
    }
}
