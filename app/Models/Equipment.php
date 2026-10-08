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
    'item_code', 'property_number', 'serial_number', 'name', 'description', 'category',
    'is_consumable', 'document_type', 'unit',
    'quantity_on_hand', 'unit_cost', 'reorder_level', 'room_id', 'teacher_id',
    'custodian', 'status', 'date_acquired',
])]
class Equipment extends Model
{
    use TracksRoomLocation;
    public const STATUSES = ['available', 'issued', 'damaged', 'missing', 'misplaced'];

    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:2',
            'date_acquired' => 'date',
            'is_consumable' => 'boolean',
        ];
    }

    /**
     * Equipment is property unless the office marks it consumable, so the
     * classification is decided by that answer and by the unit cost: above the
     * Property Classification Threshold a PAR, at or below it an ICS. It is
     * re-derived on every save, which is what keeps an item's label right after
     * a receipt at a different price.
     *
     * The consumable answer is written with the record too, so the model in hand
     * carries the same answer the column will hold (equipment is property unless
     * the office says otherwise).
     */
    protected static function booted(): void
    {
        static::saving(function (Equipment $equipment) {
            // A new custodian supplies the default location only when the item
            // has no explicit room. Later room transfers remain independent.
            if ($equipment->isDirty('teacher_id') && $equipment->teacher_id && ! $equipment->room_id) {
                $equipment->room_id = Teacher::find($equipment->teacher_id)?->room_id;
            }

            $equipment->is_consumable ??= false;
            $equipment->document_type = ItemClassifier::forItem($equipment);
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

    /**
     * Narrow the register to what a reader is looking for: the item's name and
     * codes, its category, the custodian holding it, and the number of the paper
     * it is printed on. Shared by the room inventory search and the register's
     * own, so both look the same things up.
     */
    public function scopeMatching(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $sub) use ($term) {
            $sub->where('equipment.name', 'like', "%{$term}%")
                ->orWhere('equipment.item_code', 'like', "%{$term}%")
                ->orWhere('equipment.property_number', 'like', "%{$term}%")
                ->orWhere('equipment.serial_number', 'like', "%{$term}%")
                ->orWhere('equipment.category', 'like', "%{$term}%")
                ->orWhere('equipment.custodian', 'like', "%{$term}%")
                ->orWhereHas('teacher', function ($teachers) use ($term) {
                    $teachers->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhereRaw("concat(first_name, ' ', last_name) like ?", ["%{$term}%"]);
                })
                ->orWhereHas('documentItems.document', fn ($document) => $document->where('document_no', 'like', "%{$term}%"));
        });
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
