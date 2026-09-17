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

    public const TYPE_TITULO_PROPIEDAD = 'TITULO_PROPIEDAD';

    public const TYPE_LICENCIA = 'LICENCIA';

    public const TYPE_CONVENIO = 'CONVENIO';

    public const TYPE_OTRO = 'OTRO';

    public const TYPES = [
        self::TYPE_TITULO_PROPIEDAD => 'Título de propiedad',
        self::TYPE_LICENCIA => 'Licencia de funcionamiento',
        self::TYPE_CONVENIO => 'Convenio',
        self::TYPE_OTRO => 'Otro',
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
