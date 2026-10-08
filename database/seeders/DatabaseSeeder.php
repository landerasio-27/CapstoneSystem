<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            RoomSeeder::class,
            TeacherSeeder::class,
            EquipmentSeeder::class,
            SupplySeeder::class,
            // The papers come last: a document is cut from the people, rooms
            // and items the seeders above have already put on file.
            DocumentSeeder::class,
        ]);
    }
}
