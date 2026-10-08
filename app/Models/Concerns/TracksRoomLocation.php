<?php

namespace App\Models\Concerns;

use App\Models\Room;
use App\Models\RoomMovement;

trait TracksRoomLocation
{
    public ?string $roomMovementReason = null;

    public ?string $roomMovementNotes = null;

    public static function bootTracksRoomLocation(): void
    {
        static::saved(function ($item) {
            $created = $item->getRawOriginal('id') === null;
            if (! $item->wasChanged('room_id') && ! ($created && $item->room_id)) {
                return;
            }

            $previousId = $created ? null : $item->getRawOriginal('room_id');
            $from = $previousId ? Room::find($previousId) : null;
            $to = $item->room_id ? Room::find($item->room_id) : null;

            RoomMovement::record(
                $item,
                $from,
                $to,
                $from ? ($to ? 'transferred' : 'removed') : 'added',
                $item->roomMovementReason,
                $item->roomMovementNotes,
            );

            // Location changes also make both room folders recently updated.
            Room::whereIn('id', array_filter([$previousId, $item->room_id]))->update(['updated_at' => now()]);
            $item->roomMovementReason = null;
            $item->roomMovementNotes = null;
        });
    }
}
