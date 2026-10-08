<?php

namespace Database\Seeders;

use App\Models\Room;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $rooms = [
            ['code' => 'A101', 'name' => 'Computer Laboratory 1', 'building' => 'Main Building A', 'floor' => '1st Floor', 'type' => 'laboratory'],
            ['code' => 'A102', 'name' => 'Computer Laboratory 2', 'building' => 'Main Building A', 'floor' => '1st Floor', 'type' => 'laboratory'],
            ['code' => 'A201', 'name' => 'Faculty Room - CCS', 'building' => 'Main Building A', 'floor' => '2nd Floor', 'type' => 'office'],
            ['code' => 'B101', 'name' => 'Lecture Room 1', 'building' => 'Building B', 'floor' => '1st Floor', 'type' => 'classroom'],
            ['code' => 'B102', 'name' => 'Lecture Room 2', 'building' => 'Building B', 'floor' => '1st Floor', 'type' => 'classroom'],
            ['code' => 'SUP-01', 'name' => 'Supply Office Storage', 'building' => 'Admin Building', 'floor' => 'Ground Floor', 'type' => 'storage'],
        ];

        foreach ($rooms as $room) {
            Room::updateOrCreate(['code' => $room['code']], $room);
        }
    }
}
