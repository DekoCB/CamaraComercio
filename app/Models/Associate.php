<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Column meaning follows the Cámara's master Excel ("DATA DE ASOCIADOS"):
 * `name` is the RAZÓN SOCIAL, `company` the NOMBRE COMERCIAL and `email`
 * the CORREO DE LA EMPRESA. The catalog constants below are the closed
 * value lists that spreadsheet uses, shared by the form, the request
 * rules and the importer so all three agree on what is accepted.
 */
class Associate extends Model
{
    use HasFactory;

    public const STATUS_ACTIVO = 'ACTIVO';

    public const STATUS_SUSPENDIDO = 'SUSPENDIDO';

    public const STATUS_DESAFILIADO = 'DESAFILIADO';

    public const STATUSES = [self::STATUS_ACTIVO, self::STATUS_SUSPENDIDO, self::STATUS_DESAFILIADO];

    public const PERSON_TYPES = ['PERSONA JURÍDICA', 'PERSONA NATURAL'];

    /**
     * Suggestions only (free text in the form and importer): the master
     * Excel showed too few distinct values to be sure the list is closed.
     */
    public const COMPANY_SIZES = ['MICROEMPRESAS', 'PEQUEÑA EMPRESA', 'MEDIANA EMPRESA', 'GRAN EMPRESA'];

    public const ACTIVITY_TYPES = ['SERVICIO', 'COMERCIO', 'INDUSTRIALES'];

    public const GENDERS = ['MASCULINO', 'FEMENINO'];

    /**
     * "Actividad Principal"/"Actividades Complementarias" en la Ficha de
     * Inscripción — mismo catálogo cerrado de 6 opciones para ambas.
     */
    public const ACTIVITY_OPTIONS = [
        'FABRICANTE', 'PRODUCTOR', 'COMERCIALIZADOR', 'IMPORTADOR', 'EXPORTADOR', 'SERVICIOS',
    ];

    /**
     * "En mi calidad de aspirante / asociado" en la Declaración Jurada —
     * a diferencia de `status` (ACTIVO/SUSPENDIDO/DESAFILIADO), esto no
     * se guarda en el asociado: es una elección de ese documento puntual,
     * no un estado permanente.
     */
    public const MEMBERSHIP_DECLARATION_OPTIONS = ['ASPIRANTE', 'ASOCIADO'];

    protected $fillable = [
        'name',
        'status',
        'sectorista',
        'category',
        'monthly_fee',
        'joined_at',
        'person_type',
        'anniversary_date',
        'ruc',
        'company',
        'contact_phone',
        'email',
        'billing_address',
        'billing_district',
        'mailing_address',
        'mailing_district',
        'company_size',
        'activity_type',
        'sector_committee',
        'ciiu',
        'sub_sector',
        'legal_rep_name',
        'legal_rep_dni',
        'legal_rep_gender',
        'legal_rep_birthday',
        'legal_rep_phone',
        'legal_rep_email',
        'legal_rep_position',
        'cch_rep_name',
        'cch_rep_dni',
        'cch_rep_gender',
        'cch_rep_birthday',
        'cch_rep_phone',
        'cch_rep_email',
        'cch_rep_position',
        'image_path',
        'notes',
        'is_active',
        'activities_started_at',
        'billing_province',
        'billing_department',
        'website',
        'profession',
        'public_registry_entry',
        'public_registry_title',
        'main_activity',
        'complementary_activities',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'monthly_fee' => 'decimal:2',
            'joined_at' => 'date',
            'anniversary_date' => 'date',
            'legal_rep_birthday' => 'date',
            'cch_rep_birthday' => 'date',
            'activities_started_at' => 'date',
            'complementary_activities' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // `is_active` is what billing and the rest of the app filter on;
        // `status` is the finer-grained value the Cámara actually tracks
        // (suspended vs. unaffiliated). Keep the flag derived so no code
        // path can leave the two disagreeing.
        static::saving(function (Associate $associate) {
            // Legacy writes that only touch the flag (factories, seeders)
            // still land on a coherent status.
            if ($associate->isDirty('is_active') && ! $associate->isDirty('status')) {
                $associate->status = $associate->is_active
                    ? self::STATUS_ACTIVO
                    : (in_array($associate->status, [self::STATUS_SUSPENDIDO, self::STATUS_DESAFILIADO], true) ? $associate->status : self::STATUS_SUSPENDIDO);
            }
            $associate->status ??= self::STATUS_ACTIVO;
            $associate->is_active = $associate->status === self::STATUS_ACTIVO;
        });
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(AssociateDocument::class);
    }

    public function benefitUsages(): HasMany
    {
        return $this->hasMany(BenefitUsage::class);
    }

    public function executives(): HasMany
    {
        return $this->hasMany(AssociateExecutive::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(AssociateProduct::class);
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    /**
     * "ULT. MES PAGO" in the Excel — derived from paid invoices rather
     * than stored, so it can never drift from the payments actually
     * registered. Returns the YYYY-MM period or null when nothing is paid.
     */
    public function lastPaidPeriod(): ?string
    {
        return $this->invoices()->where('status', Invoice::STATUS_PAGADA)->max('period');
    }
}
