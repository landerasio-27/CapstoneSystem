<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rooms as inventory folders.
 *
 * A room already carried where it is (building, floor, type); this adds what it
 * is for (department, a description the office can write in its own words) and
 * the record of what has moved through it.
 *
 * `room_movements` is a history, not a second copy of the inventory: an item's
 * location is still the single `room_id` on the equipment or supply row. A row
 * here records one move — which room it left, which room it arrived in, why, who
 * did it — so the room page can show what happened to it. The item's name and
 * code are written on the row as they were at the time, so a later rename does
 * not rewrite the past.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->string('department')->nullable()->after('floor');
            $table->text('description')->nullable()->after('type');

            $table->index('type');
        });

        Schema::create('room_movements', function (Blueprint $table) {
            $table->id();

            // What moved: an equipment row or a supply row. No foreign key —
            // the pair is polymorphic, and the history outlives the item.
            $table->string('item_type');
            $table->unsignedBigInteger('item_id');
            $table->string('item_label');
            $table->string('item_code')->nullable();

            // added (placed in a room), transferred (room to room), removed.
            $table->string('action');

            // Where it came from and where it went: one of them is null when an
            // item is placed in or taken out of the register's rooms.
            $table->foreignId('from_room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->foreignId('to_room_id')->nullable()->constrained('rooms')->nullOnDelete();

            $table->string('reason')->nullable();
            $table->text('notes')->nullable();

            // Who performed it. Never typed by hand — the authenticated user.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['item_type', 'item_id']);
            $table->index(['to_room_id', 'created_at']);
            $table->index(['from_room_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_movements');

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn(['department', 'description']);
        });
    }
};
