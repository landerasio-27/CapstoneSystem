<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'card_id', 'transaction_date', 'type', 'reference', 'receipt_qty', 'issue_qty',
    'office', 'balance_qty', 'amount', 'remarks', 'inventory_id', 'document_item_id',
    'equipment_transaction_id', 'created_by',
])]
class CardTransaction extends Model
{
    /** Human wording for the line type, written the way the forms read. */
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

    /** Signed quantity for display, e.g. "+100" / "-20". */
    public function signedQuantity(): string
    {
        return $this->receipt_qty > 0
            ? '+' . $this->receipt_qty
            : '-' . $this->issue_qty;
    }

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The inventory movement this line was projected from, if any. */
    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    /** The document line this line was projected from, if any. */
    public function documentItem(): BelongsTo
    {
        return $this->belongsTo(DocumentItem::class);
    }

    /** The equipment receipt this line was projected from, if any. */
    public function equipmentTransaction(): BelongsTo
    {
        return $this->belongsTo(EquipmentTransaction::class);
    }

    /** True when the line was projected from another record rather than posted by hand. */
    public function isProjected(): bool
    {
        return $this->inventory_id !== null || $this->document_item_id !== null || $this->equipment_transaction_id !== null;
    }
}
