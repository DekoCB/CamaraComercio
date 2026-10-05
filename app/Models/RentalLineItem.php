<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalLineItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'rental_id',
        'catalog_item_id',
        'description',
        'quantity',
        'hourly_rate',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
        ];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(RentalCatalogItem::class, 'catalog_item_id');
    }

    public function label(): string
    {
        return $this->description ?: ($this->catalogItem->name ?? 'Ítem');
    }

    public function total(): float
    {
        return round((float) $this->quantity * (float) ($this->hourly_rate ?? 0), 2);
    }
}
