<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'supply_id', 'type', 'quantity', 'unit_cost', 'total_cost', 'balance_after',
    'reference_type', 'reference_id', 'reference', 'remarks', 'user_id',
])]
class Inventory extends Model
{
    protected $table = 'inventory';

    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
        ];
    }

    public function supply(): BelongsTo
    {
        return $this->belongsTo(Supply::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /** Human readable label for the movement type. */
    public function typeLabel(): string
    {
        return match ($this->type) {
            'in' => 'Stock In',
            'out' => 'Stock Out',
            default => 'Adjustment',
        };
    }

    /** Quantity with an explicit sign, e.g. "+100" / "-25". */
    public function signedQuantity(): string
    {
        return ($this->quantity > 0 ? '+' : '') . $this->quantity;
    }
}
