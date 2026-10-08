<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'document_id', 'equipment_id', 'supply_id', 'description', 'unit',
    'quantity', 'unit_cost', 'amount', 'property_number',
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
        ];
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
