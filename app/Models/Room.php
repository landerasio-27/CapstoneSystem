<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * A room is where the inventory lives.
 *
 * Nothing is stored on the room itself: equipment and supplies each carry the
 * `room_id` they sit in, a custodian carries the room they work in, and a
 * document carries the room it was raised for. This model is the reference
 * point those records are read against — the room page is assembled from them,
 * so an item moved anywhere in the system appears in the new room and leaves
 * the old one with nothing to re-count by hand.
 *
 * The `type` values are the office's own list of room kinds; `department` is
 * the college or office the room belongs to.
 */
#[Fillable(['code', 'name', 'building', 'floor', 'department', 'type', 'description', 'is_active'])]
class Room extends Model
{
    /**
     * The kinds of room the register keeps, keyed by the value stored in the
     * database and read as the label the office uses.
     */
    public const TYPES = [
        'office' => 'Office',
        'classroom' => 'Classroom',
        'laboratory' => 'Laboratory',
        'faculty_room' => 'Faculty Room',
        'storage' => 'Storage Room',
        'conference_room' => 'Conference Room',
        'other' => 'Other',
    ];

    /**
     * The quick views over the register: one filter per group of room kinds, so
     * the page does not need a pill for every value in TYPES.
     */
    public const TYPE_GROUPS = [
        'offices' => ['office', 'faculty_room'],
        'classrooms' => ['classroom', 'conference_room'],
        'laboratories' => ['laboratory'],
        'storage' => ['storage'],
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function teachers(): HasMany
    {
        return $this->hasMany(Teacher::class);
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class);
    }

    public function supplies(): HasMany
    {
        return $this->hasMany(Supply::class);
    }

    /** The papers raised for this room (a document's own `room_id`). */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /** What has arrived in this room. */
    public function arrivals(): HasMany
    {
        return $this->hasMany(RoomMovement::class, 'to_room_id');
    }

    /** What has left this room. */
    public function departures(): HasMany
    {
        return $this->hasMany(RoomMovement::class, 'from_room_id');
    }

    /**
     * Everything that moved through the room — in or out — newest first. A
     * transfer between two rooms is one row, and it appears in both rooms'
     * histories: as an arrival in one and a departure in the other.
     *
     * @return Collection<int, RoomMovement>
     */
    public function history(int $limit = 40): Collection
    {
        return RoomMovement::with(['fromRoom', 'toRoom', 'user'])
            ->where(function ($query) {
                $query->where('from_room_id', $this->id)
                    ->orWhere('to_room_id', $this->id);
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /** The kind of room, as a person reads it. */
    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ($this->type ? ucfirst((string) $this->type) : '—');
    }

    /** How many names it answers to: its code, its name and its room number. */
    public function title(): string
    {
        return trim($this->code.' — '.$this->name, ' —');
    }

    /** Everything physically in the room, property and supplies together. */
    public function inventoryCount(): int
    {
        return (int) ($this->equipment_count ?? 0) + (int) ($this->supplies_count ?? 0);
    }
}
