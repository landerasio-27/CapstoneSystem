<?php

namespace Database\Seeders;

use App\Models\Equipment;
use App\Models\Room;
use App\Models\Teacher;
use Illuminate\Database\Seeder;

class EquipmentSeeder extends Seeder
{
    /**
     * Master information only.
     *
     * No unit cost is seeded: a price belongs to the receipt that brought the
     * unit in, so it is recorded with Add Quantity and kept in the equipment's
     * movement history. An item with no receipt therefore shows no cost rather
     * than a seeded figure that nobody actually paid.
     */
    public function run(): void
    {
        $items = [
            ['item_code' => 'EQP-00001', 'name' => 'Dell OptiPlex Desktop Computer', 'category' => 'ICT Equipment', 'teacher' => 'FAC-0001', 'room' => 'A101'],
            ['item_code' => 'EQP-00002', 'name' => 'Epson LCD Projector', 'category' => 'ICT Equipment', 'teacher' => 'FAC-0001', 'room' => 'A101'],
            ['item_code' => 'EQP-00003', 'name' => 'HP LaserJet Printer', 'category' => 'ICT Equipment', 'teacher' => 'FAC-0002', 'room' => 'A102'],
            ['item_code' => 'EQP-00004', 'name' => 'Split-Type Air Conditioner 2HP', 'category' => 'Appliances', 'teacher' => 'FAC-0002', 'room' => 'A102'],
            ['item_code' => 'EQP-00005', 'name' => 'Office Swivel Chair', 'category' => 'Furniture', 'teacher' => 'FAC-0003', 'room' => 'B101'],
            ['item_code' => 'EQP-00006', 'name' => 'Wooden Teacher\'s Table', 'category' => 'Furniture', 'teacher' => 'FAC-0003', 'room' => 'B101'],
            ['item_code' => 'EQP-00007', 'name' => 'Whiteboard 4x8ft', 'category' => 'Instructional Materials', 'teacher' => 'FAC-0004', 'room' => 'B102'],
            ['item_code' => 'EQP-00008', 'name' => 'Wireless Microphone Set', 'category' => 'ICT Equipment', 'teacher' => 'FAC-0004', 'room' => 'B102'],
        ];

        foreach ($items as $item) {
            $teacher = Teacher::where('employee_id', $item['teacher'])->first();
            $room = Room::where('code', $item['room'])->first();

            Equipment::updateOrCreate(['item_code' => $item['item_code']], [
                'property_number' => 'PN-'.substr($item['item_code'], -5),
                'name' => $item['name'],
                'description' => $item['name'].' for classroom/laboratory use.',
                'category' => $item['category'],
                'unit' => 'unit',
                'quantity_on_hand' => 0,
                'reorder_level' => 0,
                'room_id' => $room?->id,
                'teacher_id' => $teacher?->id,
                'custodian' => $teacher?->full_name,
                'status' => 'available',
                'date_acquired' => now()->subYear(),
            ]);
        }
    }
}
