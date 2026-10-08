<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\Supply;
use Illuminate\Database\Seeder;

class SupplySeeder extends Seeder
{
    /**
     * Master information and the opening quantity only.
     *
     * No unit cost is seeded: a price belongs to the stock-in that brought the
     * item in, so it is recorded with Add Stock and kept in the stock movement
     * history. Seeding it here also meant a re-run could overwrite the cost a real
     * delivery had recorded.
     */
    public function run(): void
    {
        $storage = Room::where('code', 'SUP-01')->first();

        $supplies = [
            ['item_code' => 'SUP-00001', 'name' => 'Bond Paper A4 (Ream)', 'category' => 'Office Supplies', 'unit' => 'ream', 'qty' => 0, 'reorder' => 30],
            ['item_code' => 'SUP-00002', 'name' => 'Ballpoint Pen (Black)', 'category' => 'Office Supplies', 'unit' => 'piece', 'qty' => 0, 'reorder' => 50],
            ['item_code' => 'SUP-00003', 'name' => 'Printer Ink Bottle (Black)', 'category' => 'ICT Supplies', 'unit' => 'bottle', 'qty' => 0, 'reorder' => 10],
            ['item_code' => 'SUP-00004', 'name' => 'Whiteboard Marker', 'category' => 'Instructional Supplies', 'unit' => 'piece', 'qty' => 0, 'reorder' => 20],
            ['item_code' => 'SUP-00005', 'name' => 'Folder (Long, Brown)', 'category' => 'Office Supplies', 'unit' => 'piece', 'qty' => 0, 'reorder' => 50],
        ];

        foreach ($supplies as $supply) {
            Supply::updateOrCreate(['item_code' => $supply['item_code']], [
                'name' => $supply['name'],
                'description' => $supply['name'],
                'category' => $supply['category'],
                'unit' => $supply['unit'],
                'quantity_on_hand' => $supply['qty'],
                'reorder_level' => $supply['reorder'],
                'room_id' => $storage?->id,
                'is_active' => true,
            ]);
        }
    }
}
