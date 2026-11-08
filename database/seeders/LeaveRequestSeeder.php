<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\LeaveRequest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/** Demo leave ledger states for the Off Day Requests workspace. */
class LeaveRequestSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::query()->selectable()->active()->orderBy('id')->take(8)->get();
        if ($employees->isEmpty()) {
            return;
        }

        $samples = [
            ['reason' => 'Medical appointment', 'duration' => 0.5, 'granted' => false, 'offDay' => 0],
            ['reason' => 'Family commitment', 'duration' => 1, 'granted' => false, 'offDay' => 0],
            ['reason' => 'Personal travel', 'duration' => 1, 'granted' => true, 'offDay' => 1],
            ['reason' => 'Urgent family matter', 'duration' => 1, 'granted' => true, 'offDay' => 0.5],
            ['reason' => 'Exhausted allowance request', 'duration' => 1, 'granted' => true, 'offDay' => 0],
            ['reason' => 'Study commitment', 'duration' => 0.5, 'granted' => true, 'offDay' => 0.5],
            ['reason' => 'Home emergency', 'duration' => 1, 'granted' => false, 'offDay' => 0],
            ['reason' => 'Religious observance', 'duration' => 1, 'granted' => true, 'offDay' => 1],
        ];

        foreach ($employees->values() as $index => $employee) {
            $sample = $samples[$index];
            $date = now()->startOfYear()->addDays(14 + ($index * 9));
            if ($date->isFriday()) {
                $date->addDay();
            }

            LeaveRequest::firstOrCreate(
                ['employee_id' => $employee->id, 'date' => $date->toDateString()],
                [
                    'year' => $date->year,
                    'duration_days' => $sample['duration'],
                    'off_day_days' => $sample['offDay'],
                    'reason' => $sample['reason'],
                    'is_granted' => $sample['granted'],
                    'granted_at' => $sample['granted'] ? Carbon::now() : null,
                ],
            );
        }
    }
}
