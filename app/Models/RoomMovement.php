<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One recorded move of an item into, out of or between rooms.
 *
 * The room an item sits in is the single `room_id` on its own row; this is the
 * record of how it got there. A move writes one row — the room it left, the room
 * it arrived in, why, and who did it — so the room page can show its history
 * without a second copy of the inventory to keep in step.
 */
#[Fillable([
    'item_type', 'item_id', 'item_label', 'item_code', 'action',
    'from_room_id', 'to_room_id', 'reason', 'notes', 'user_id',
])]
class RoomMovement extends Model
{
    /** The kinds of item the history covers. */
    public const ITEM_TYPES = ['equipment' => 'Equipment', 'supply' => 'Supply'];

    /** What happened to the item. */
    public const ACTIONS = [
        'added' => 'Added',
        'transferred' => 'Transferred',
        'removed' => 'Removed',
    ];

    /**
     * Why an item was moved. Chosen from a fixed list rather than typed, so the
     * history can be read at a glance and counted later.
     */
    public const REASONS = [
        'reassignment' => 'Reassignment of office',
        'relocation' => 'Relocation of item',
        'repair' => 'For repair',
        'storage' => 'Returned to storage',
        'instruction' => 'For instruction / laboratory use',
        'inspection' => 'For inspection',
        'other' => 'Other',
    ];

    /**
     * Write one movement row for an item that has just been placed somewhere.
     *
     * Called by the room module itself, so every change of location leaves the
     * same kind of record behind, whichever page performs it.
     */
    public static function record(
        Equipment|Supply $item,
        ?Room $from,
        ?Room $to,
        string $action,
        ?string $reason = null,
        ?string $notes = null,
    ): self {
        return self::create([
            'item_type' => $item instanceof Equipment ? 'equipment' : 'supply',
            'item_id' => $item->id,
            'item_label' => (string) $item->name,
            'item_code' => $item->item_code,
            'action' => $action,
            'from_room_id' => $from?->id,
            'to_room_id' => $to?->id,
            'reason' => $reason,
            'notes' => $notes,
            'user_id' => auth()->id(),
        ]);
    }

    public function fromRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'from_room_id');
    }

    public function toRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'to_room_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The kind of item that moved, as a person reads it. */
    public function typeLabel(): string
    {
        return self::ITEM_TYPES[$this->item_type] ?? ucfirst($this->item_type);
    }

    /** What happened, as a person reads it. */
    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action] ?? ucfirst($this->action);
    }

    /** Why it moved, as a person reads it. */
    public function reasonLabel(): ?string
    {
        return $this->reason ? (self::REASONS[$this->reason] ?? ucfirst($this->reason)) : null;
    }

    /**
     * The item's own page. The row keeps only the pair, so the destination is
     * built from the item type rather than stored as a URL.
     */
    public function itemUrl(): ?string
    {
        return match ($this->item_type) {
            'equipment' => $this->item_id ? route('admin.equipment.show', $this->item_id) : null,
            'supply' => $this->item_id ? route('admin.supplies.show', $this->item_id) : null,
            default => null,
        };
    }

    /**
     * The one line the history prints for this move: where it came from and
     * where it went, with a placement or a removal said plainly.
     */
    public function description(): string
    {
        $target = $this->toRoom?->code ?? $this->fromRoom?->code;

        return match ($this->action) {
            'added' => "Placed in {$target}",
            'removed' => "Taken out of {$target}",
            default => 'Transferred from '.($this->fromRoom?->code ?? '—').' to '.($this->toRoom?->code ?? '—'),
        };
    }
}
