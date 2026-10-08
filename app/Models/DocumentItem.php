<?php

namespace App\Models;

use App\Services\ItemClassifier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'document_id', 'equipment_id', 'supply_id', 'description', 'unit',
    'quantity', 'unit_cost', 'is_consumable', 'document_type', 'amount', 'property_number',
    'date_acquired', 'estimated_useful_life', 'stock_available', 'issued_quantity', 'item_remarks',
])]
class DocumentItem extends Model
{
    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:2',
            'amount' => 'decimal:2',
            'date_acquired' => 'date',
            'is_consumable' => 'boolean',
        ];
    }

    /**
     * The paper this line belongs on, as classified when it was saved. The line
     * carries its own copy so a paper's lines can be read (and filtered) without
     * re-deriving the rule from the item's current price.
     */
    public function classification(): string
    {
        return $this->document_type ?: ItemClassifier::for((bool) $this->is_consumable, $this->unit_cost);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function supply(): BelongsTo
    {
        return $this->belongsTo(Supply::class);
    }
}
