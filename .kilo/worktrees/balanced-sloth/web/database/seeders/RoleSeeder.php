<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::updateOrCreate(['name' => Role::ADMIN], [
            'display_name' => 'Admin',
            'description' => 'Supply office administrator with full management access to the web portal.',
        ]);

        Role::updateOrCreate(['name' => Role::INSPECTION_STAFF], [
            'display_name' => 'Supply Staff',
            'description' => 'Supply office staff account: the register and the reports, without the administration.',
        ]);
    }
}
