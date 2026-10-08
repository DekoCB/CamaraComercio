<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un trámite de emisión de placa vehicular (oct-2026) — nueva placa,
 * duplicado, tercera placa, boleta informativa, u otro trámite. Cada uno
 * ya quedó cobrado (factura o boleta) al momento de registrarse, así que
 * no hay edición ni cancelación posterior — mismo criterio que Protest:
 * un registro de un servicio ya prestado, nunca borrado físicamente.
 */
class PlateIssuance extends Model
{
    use HasFactory;

    public const PROCEDURE_NUEVA = 'NUEVA_PLACA';

    public const PROCEDURE_DUPLICADO = 'DUPLICADO';

    public const PROCEDURE_TERCERA = 'TERCERA_PLACA';

    public const PROCEDURE_BOLETA_INFORMATIVA = 'BOLETA_INFORMATIVA';

    public const PROCEDURE_OTROS = 'OTROS';

    public const PROCEDURE_TYPES = [
        self::PROCEDURE_NUEVA => 'Nueva placa',
        self::PROCEDURE_DUPLICADO => 'Duplicado de placa',
        self::PROCEDURE_TERCERA => 'Tercera placa',
        self::PROCEDURE_BOLETA_INFORMATIVA => 'Boleta informativa',
        self::PROCEDURE_OTROS => 'Otros',
    ];

    public const RECEIPT_FACTURA = 'FACTURA';

    public const RECEIPT_BOLETA = 'BOLETA';

    public const RECEIPT_TYPES = [
        self::RECEIPT_FACTURA => 'Factura',
        self::RECEIPT_BOLETA => 'Boleta',
    ];

    protected $fillable = [
        'procedure_type',
        'other_description',
        'plate_number',
        'associate_id',
        'client_name',
        'vehicle_description',
        'receipt_type',
        'receipt_number',
        'amount',
        'issued_at',
        'notes',
        'registered_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'issued_at' => 'date',
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

    public function procedureLabel(): string
    {
        return self::PROCEDURE_TYPES[$this->procedure_type] ?? $this->procedure_type;
    }

    public function receiptLabel(): string
    {
        return self::RECEIPT_TYPES[$this->receipt_type] ?? $this->receipt_type;
    }

    /** "Sres. ___" del trámite — el asociado si lo es, si no el nombre libre. */
    public function requesterLabel(): string
    {
        return $this->associate->name ?? $this->client_name ?? '-';
    }

    /** Clave de Setting con la tarifa por defecto de cada tipo de trámite. */
    public static function rateSettingKey(string $procedureType): string
    {
        return "plates.rate.{$procedureType}";
    }
}
