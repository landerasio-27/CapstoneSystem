<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'item_code', 'name', 'description', 'category', 'unit', 'quantity_on_hand',
    'unit_cost', 'reorder_level', 'room_id', 'is_active',
])]
class Supply extends Model
{
    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function requestItems(): HasMany
    {
        return $this->hasMany(SupplyRequestItem::class);
    }

    public function documentItems(): HasMany
    {
        return $this->hasMany(DocumentItem::class);
    }

    /**
     * At or below the re-order point the office set for this item. Used by the
     * list badge, the low-stock filter and the dashboard's Healthy/Low/Critical
     * status, and printed on the item's Stock Card.
     */
    public function isLowStock(): bool
    {
        return $this->quantity_on_hand <= $this->reorder_level;
    }

    /**
     * Unit cost of the most recent stock-in transaction. Cost belongs to the
     * stock transaction, not to the product record, so this is the read-only
     * figure shown on the supply details page.
     */
    public function latestUnitCost(): ?float
    {
        $cost = $this->inventoryMovements()
            ->where('type', 'in')
            ->whereNotNull('unit_cost')
            ->latest()
            ->value('unit_cost');

        return $cost === null ? null : (float) $cost;
    }
}
