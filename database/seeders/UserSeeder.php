<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('name', Role::ADMIN)->firstOrFail();
        $staffRole = Role::where('name', Role::INSPECTION_STAFF)->firstOrFail();

        User::updateOrCreate(['email' => 'admin@isu.edu.ph'], [
            'role_id' => $adminRole->id,
            'name' => 'Supply Office Administrator',
            'employee_id' => 'ADM-0001',
            'contact_number' => '09171234567',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $inspectors = [
            ['name' => 'Juan Dela Cruz', 'employee_id' => 'INS-0001', 'email' => 'jdelacruz@isu.edu.ph'],
            ['name' => 'Maria Santos', 'employee_id' => 'INS-0002', 'email' => 'msantos@isu.edu.ph'],
        ];

        foreach ($inspectors as $inspector) {
            User::updateOrCreate(['email' => $inspector['email']], [
                'role_id' => $staffRole->id,
                'name' => $inspector['name'],
                'employee_id' => $inspector['employee_id'],
                'contact_number' => '09171234568',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]);
        }
    }
}
