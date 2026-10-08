<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'item_code', 'property_number', 'serial_number', 'name', 'description', 'category', 'unit',
    'quantity_on_hand', 'unit_cost', 'reorder_level', 'room_id', 'teacher_id',
    'custodian', 'status', 'date_acquired',
])]
class Equipment extends Model
{
    public const STATUSES = ['available', 'issued', 'damaged', 'missing', 'misplaced'];

    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:2',
            'date_acquired' => 'date',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * The custodian's name: the person chosen on the form, falling back to a
     * name typed into the legacy free-text column for records saved while that
     * field existed.
     */
    public function getCustodianNameAttribute(): string
    {
        return (string) ($this->teacher?->full_name ?: $this->custodian);
    }

    public function documentItems(): HasMany
    {
        return $this->hasMany(DocumentItem::class);
    }

    /**
     * When this item last changed hands: the date on its latest issue or
     * transfer, falling back to the date it was acquired (or, for a record with
     * neither, the day it was entered). The custodian folder prints this as the
     * "assigned" date.
     */
    public function assignedAt(): ?\Illuminate\Support\Carbon
    {
        $movement = $this->relationLoaded('transactions')
            ? $this->transactions->first(
                fn ($transaction) => in_array($transaction->type, ['issue', 'transfer'], true)
            )
            : $this->transactions()
                ->whereIn('type', ['issue', 'transfer'])
                ->orderByDesc('transaction_date')
                ->orderByDesc('id')
                ->first();

        return $movement?->transaction_date ?? $this->date_acquired ?? $this->created_at;
    }

    /**
     * The equipment ledger: one row per receipt/issue/transfer/disposal, newest
     * activity is ordered by the caller. Quantity and unit cost are never set by
     * hand on the record itself, only through these transactions.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(EquipmentTransaction::class);
    }

    /** Current holding value: quantity on hand at the current unit cost. */
    public function totalValue(): float
    {
        return round((float) $this->quantity_on_hand * (float) $this->unit_cost, 2);
    }

    /**
     * Kept only so existing low-stock indicators keep working. The reorder
     * point is no longer part of the product form, so an item either carries
     * the threshold it already had or none at all (0).
     *
     * @deprecated No longer settable through any form or endpoint.
     */
    public function isLowStock(): bool
    {
        return $this->quantity_on_hand <= $this->reorder_level;
    }
}
