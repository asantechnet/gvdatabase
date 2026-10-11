<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Office;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $officeId = Office::query()->where('code', 'HQ')->value('id');
        $employees = Employee::query()->where('status', Employee::STATUS_ACTIVE)->orderBy('id')->get();
        if ($employees->isEmpty()) {
            return;
        }

        // The most recent workdays. Statuses rotate so the ledger is not
        // uniformly "present".
        $statuses = ['present', 'present', 'present', 'absent', 'present'];

        for ($daysAgo = 1; $daysAgo <= 5; $daysAgo++) {
            $date = now()->subDays($daysAgo);

            foreach ($employees as $index => $employee) {
                $status = $statuses[($index + $daysAgo) % count($statuses)];

                Attendance::updateOrCreate(
                    ['employee_id' => $employee->id, 'date' => $date->toDateString()],
                    [
                        'status' => $status,
                        'logged_at' => $status === 'present' ? '09:0'.($index % 6).':00' : '10:15:00',
                        'office_id' => $officeId,
                    ],
                );
            }
        }
    }
}
