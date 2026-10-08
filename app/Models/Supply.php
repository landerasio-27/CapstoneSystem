<?php

namespace App\Models;

use App\Models\Concerns\TracksRoomLocation;
use App\Services\ItemClassifier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'item_code', 'name', 'description', 'category', 'is_consumable', 'document_type', 'unit', 'quantity_on_hand',
    'unit_cost', 'reorder_level', 'room_id', 'is_active',
])]
class Supply extends Model
{
    use TracksRoomLocation;
    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:2',
            'is_active' => 'boolean',
            'is_consumable' => 'boolean',
        ];
    }

    /**
     * A supply is a consumable by default; when the office marks one as
     * non-consumable it is property, and its unit cost then decides between the
     * two property papers. The classification is re-derived on every save so a
     * new purchase price moving the item over the threshold updates it here
     * rather than leaving a stale label behind.
     *
     * The consumable answer is written with the record too, so the model in hand
     * carries the same answer the column will hold: every later read — the
     * badge, the paper, the register filter — reads the stored flag instead of
     * inferring one from the model's class.
     */
    protected static function booted(): void
    {
        static::saving(function (Supply $supply) {
            $supply->is_consumable ??= true;
            $supply->document_type = ItemClassifier::forItem($supply);
        });
    }

    /** The paper this item is recorded on: PAR, ICS or RIS. */
    public function classification(): string
    {
        return $this->document_type ?: ItemClassifier::forItem($this);
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
     * Narrow the stock to what a reader is looking for: the item's name and
     * code, its category, and the number of the paper it is printed on. Shared
     * by the room inventory search and the register's own.
     */
    public function scopeMatching(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $sub) use ($term) {
            $sub->where('supplies.name', 'like', "%{$term}%")
                ->orWhere('supplies.item_code', 'like', "%{$term}%")
                ->orWhere('supplies.category', 'like', "%{$term}%")
                ->orWhereHas('documentItems.document', fn ($document) => $document->where('document_no', 'like', "%{$term}%"));
        });
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
