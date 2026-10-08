<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One recorded equipment transaction (a receipt, an issue, a transfer, a
 * disposal or a correcting adjustment).
 *
 * Rows are append-only: a mistake is corrected by recording an adjustment, so
 * the price and quantity a piece of equipment was actually received at are
 * never overwritten by a later receipt.
 */
#[Fillable([
    'equipment_id', 'transaction_date', 'type', 'reference', 'quantity',
    'unit_cost', 'total_cost', 'balance_after', 'source', 'remarks', 'user_id',
])]
class EquipmentTransaction extends Model
{
    protected $table = 'equipment_transactions';

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'unit_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Human readable label for the transaction type. */
    public function typeLabel(): string
    {
        return match ($this->type) {
            'receipt' => 'Receipt',
            'issue' => 'Issue',
            'transfer' => 'Transfer',
            'disposal' => 'Disposal',
            default => 'Adjustment',
        };
    }

    /** Quantity with an explicit sign, e.g. "+5" / "-1". */
    public function signedQuantity(): string
    {
        return ($this->quantity > 0 ? '+' : '') . $this->quantity;
    }
}
