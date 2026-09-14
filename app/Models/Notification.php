<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Shared notification feed — every row is visible to every authenticated
 * user (the app has no per-user visibility rules for it, consistent with
 * how both existing roles already see everything, docs/DATA_PROTECTION.md
 * §2); `read_by` tracks which user ids have dismissed it.
 */
class Notification extends Model
{
    public const TYPE_INVOICE_GENERATED = 'invoice.generated';

    public const TYPE_PAYMENT_VOIDED = 'payment.voided';

    protected $fillable = [
        'type',
        'title',
        'message',
        'entity_type',
        'entity_id',
        'link',
        'read_by',
    ];

    protected function casts(): array
    {
        return [
            'read_by' => 'array',
        ];
    }

    public static function record(
        string $type,
        string $title,
        ?string $message = null,
        ?string $entityType = null,
        ?string $entityId = null,
        ?string $link = null,
    ): self {
        return self::create([
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'link' => $link,
            'read_by' => [],
        ]);
    }

    public function isReadBy(int $userId): bool
    {
        return in_array($userId, $this->read_by ?? [], true);
    }
}
