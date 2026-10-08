<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A digital Stock Card (SC) or Property Card (PC).
 *
 * The card documents one real item — a supply for an SC, a piece of equipment
 * for a PC — and its header fields are read from that record, except the
 * re-order point, which the official SC prints and only the card owns.
 */
#[Fillable([
    'type', 'card_no', 'supply_id', 'equipment_id',
    'entity_name', 'fund_cluster', 'reorder_point', 'created_by',
    // Filing: set when the card is moved into an archive year.
    'archive_id', 'archived_at',
])]
class Card extends Model
{
    use Archivable;

    public const TYPES = ['SC', 'PC'];

    public const DEFAULT_ENTITY = 'Isabela State University - Cauayan Campus';

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    public function supply(): BelongsTo
    {
        return $this->belongsTo(Supply::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Posted lines, oldest first — the order the official form is written in. */
    public function transactions(): HasMany
    {
        return $this->hasMany(CardTransaction::class)->orderBy('transaction_date')->orderBy('id');
    }

    public function isStockCard(): bool
    {
        return $this->type === 'SC';
    }

    public function typeLabel(): string
    {
        return $this->isStockCard() ? 'Stock Card' : 'Property Card';
    }

    /** The item this card documents, or null if the linked record is gone. */
    public function item(): Supply|Equipment|null
    {
        return $this->isStockCard() ? $this->supply : $this->equipment;
    }

    public function itemName(): string
    {
        return $this->item()?->name ?? 'Item no longer on record';
    }

    /** SC "Stock No." / PC "Property Number". */
    public function itemCode(): ?string
    {
        return $this->isStockCard()
            ? $this->supply?->item_code
            : $this->equipment?->property_number;
    }

    public function unit(): string
    {
        return $this->item()?->unit ?? 'unit';
    }

    public function description(): ?string
    {
        return $this->item()?->description;
    }

    /**
     * Closing balance = the last posted line's balance. Falls back to the stock
     * on hand when a stock card has no lines yet.
     */
    public function balance(): int
    {
        $latest = $this->transactions()->reorder()->orderByDesc('transaction_date')->orderByDesc('id')->first();

        if ($latest) {
            return (int) $latest->balance_qty;
        }

        return (int) ($this->supply?->quantity_on_hand ?? 0);
    }

    /**
     * Sequential card number, e.g. SC-00001. Counts the highest existing number
     * for the type so a number is never reused.
     */
    public static function nextCardNumber(string $type): string
    {
        $last = static::where('type', $type)
            ->orderByDesc('card_no')
            ->lockForUpdate()
            ->value('card_no');

        $next = $last ? ((int) substr($last, -5)) + 1 : 1;

        return sprintf('%s-%05d', $type, $next);
    }
}
