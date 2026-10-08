<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\Teacher;
use Illuminate\Database\Seeder;

class TeacherSeeder extends Seeder
{
    public function run(): void
    {
        $teachers = [
            ['employee_id' => 'FAC-0001', 'first_name' => 'Ricardo', 'last_name' => 'Ramos', 'department' => 'College of Computing Studies', 'position' => 'Instructor I', 'room' => 'A101'],
            ['employee_id' => 'FAC-0002', 'first_name' => 'Angelica', 'last_name' => 'Fernandez', 'department' => 'College of Computing Studies', 'position' => 'Assistant Professor', 'room' => 'A102'],
            ['employee_id' => 'FAC-0003', 'first_name' => 'Michael', 'last_name' => 'Tolentino', 'department' => 'College of Engineering', 'position' => 'Instructor II', 'room' => 'B101'],
            ['employee_id' => 'FAC-0004', 'first_name' => 'Cristina', 'last_name' => 'Bautista', 'department' => 'College of Business', 'position' => 'Associate Professor', 'room' => 'B102'],
        ];

        foreach ($teachers as $teacher) {
            $room = Room::where('code', $teacher['room'])->first();

            Teacher::updateOrCreate(['employee_id' => $teacher['employee_id']], [
                'first_name' => $teacher['first_name'],
                'last_name' => $teacher['last_name'],
                'department' => $teacher['department'],
                'position' => $teacher['position'],
                'email' => strtolower($teacher['first_name'][0].$teacher['last_name']).'@isu.edu.ph',
                'contact_number' => '09171234567',
                'room_id' => $room?->id,
                'is_active' => true,
            ]);
        }
    }
}
