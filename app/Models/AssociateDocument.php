<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Documentación física escaneada por asociado (título de propiedad,
 * licencia, convenios — acta2.txt [11:39]): every upload is normalized to
 * a stored PDF ("generando un PDF digitalizado") by AssociateDocumentService,
 * whether the original was already a PDF or an image from a phone/scanner.
 */
class AssociateDocument extends Model
{
    use HasFactory;

    public const TYPE_FICHA_INSCRIPCION = 'FICHA_INSCRIPCION';

    public const TYPE_DECLARACION_JURADA = 'DECLARACION_JURADA';

    public const TYPE_COPIA_DNI = 'COPIA_DNI';

    public const TYPE_FICHA_RUC = 'FICHA_RUC';

    public const TYPE_LICENCIA_FUNCIONAMIENTO = 'LICENCIA_FUNCIONAMIENTO';

    public const TYPE_VIGENCIA_PODER = 'VIGENCIA_PODER';

    public const TYPE_TRES_ULTIMOS_PVP = 'TRES_ULTIMOS_PVP';

    public const TYPE_COPIA_PRIMER_PAGO = 'COPIA_PRIMER_PAGO';

    public const TYPE_TITULO_PROPIEDAD = 'TITULO_PROPIEDAD';

    public const TYPE_CONVENIOS = 'CONVENIOS';

    public const TYPE_OTROS = 'OTROS';

    /**
     * Solo los dos primeros tienen plantilla generable
     * (AssociateInscriptionService / AssociateDeclarationService); el
     * resto siempre fue, y sigue siendo, solo para subir el escaneado.
     */
    public const TYPES = [
        self::TYPE_FICHA_INSCRIPCION => 'Ficha de Inscripción',
        self::TYPE_DECLARACION_JURADA => 'Declaración Jurada',
        self::TYPE_COPIA_DNI => 'Copia de DNI',
        self::TYPE_FICHA_RUC => 'Ficha RUC',
        self::TYPE_LICENCIA_FUNCIONAMIENTO => 'Licencia de Funcionamiento',
        self::TYPE_VIGENCIA_PODER => 'Vigencia de Poder',
        self::TYPE_TRES_ULTIMOS_PVP => '3 últimos PVP',
        self::TYPE_COPIA_PRIMER_PAGO => 'Copia de primer Pago',
        self::TYPE_TITULO_PROPIEDAD => 'Título de Propiedad',
        self::TYPE_CONVENIOS => 'Convenios',
        self::TYPE_OTROS => 'Otros',
    ];

    protected $fillable = [
        'associate_id',
        'type',
        'original_name',
        'file_path',
        'size',
        'uploaded_by',
    ];

    public function associate(): BelongsTo
    {
        return $this->belongsTo(Associate::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }
}
