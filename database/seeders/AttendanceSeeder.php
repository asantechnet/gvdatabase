<?php

namespace Database\Seeders;

use App\Models\Attendance;
use Illuminate\Database\Seeder;
use App\Models\Employee;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $today = now()->format('Y-m-d');

        // Fetch sample staff members by name or ID
        $robert = Employee::where('name', 'Robert Brown')->first();
        $sarah = Employee::where('name', 'Sarah Wilson')->first();

        if ($robert) {
            Attendance::create([
                'staff_id' => $robert->id,
                'date' => $today,
                'status' => 'present',
                'logged_at' => '09:02:00',
            ]);
        }

        if ($sarah) {
            Attendance::create([
                'staff_id' => $sarah->id,
                'date' => $today,
                'status' => 'absent',
                'logged_at' => '10:15:00',
            ]);
        }
    }
}
