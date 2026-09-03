<?php

namespace Database\Seeders;

use App\Models\WorthyEmployee;
use App\Models\Employee;
use Illuminate\Database\Seeder;

class WorthyEmployeeSeeder extends Seeder
{
    public function run(): void
    {
        // Get all employees to associate with bonuses
        $employees = Employee::all();

        // Only create bonuses if we have employees
        if ($employees->count() > 0) {
            $currentMonth = now()->format('Y-m-01');
            $lastMonth = now()->subMonth()->format('Y-m-01');
            $twoMonthsAgo = now()->subMonths(2)->format('Y-m-01');

            $mockData = [
                [
                    'employee_id' => $employees[0]->id,
                    'performance_score' => 4.9,
                    'achievement_details' => 'Consistently exceeded Q2 velocity targets by 35% without bug regressions.',
                    'suggested_amount' => 4000,
                    'selected_amount' => 4000,
                    'selected_type' => 'Performance',
                    'is_granted' => false,
                    'bonus_month' => $currentMonth,
                ],
                [
                    'employee_id' => $employees->count() > 1 ? $employees[1]->id : $employees[0]->id,
                    'performance_score' => 4.8,
                    'achievement_details' => 'Designed and delivered the complete mobile design system 2 weeks early.',
                    'suggested_amount' => 2500,
                    'selected_amount' => 2500,
                    'selected_type' => 'Task Completion',
                    'is_granted' => true,
                    'bonus_month' => $lastMonth,
                ],
                [
                    'employee_id' => $employees->count() > 2 ? $employees[2]->id : $employees[0]->id,
                    'performance_score' => 4.6,
                    'achievement_details' => 'Received outstanding feedback and secured a 3-year contract extension.',
                    'suggested_amount' => 1500,
                    'selected_amount' => 2000,
                    'selected_type' => 'Manager Recognition',
                    'is_granted' => true,
                    'bonus_month' => $twoMonthsAgo,
                ],
            ];

            // Only create the 4th bonus if we have at least 4 employees
            if ($employees->count() > 3) {
                $mockData[] = [
                    'employee_id' => $employees[3]->id,
                    'performance_score' => 4.7,
                    'achievement_details' => 'Reached 5-year tenure milestone with zero critical downtime incidents.',
                    'suggested_amount' => 3000,
                    'selected_amount' => 3000,
                    'selected_type' => 'Work Anniversary',
                    'is_granted' => false,
                    'bonus_month' => $currentMonth,
                ];
            }

            foreach ($mockData as $employee) {
                WorthyEmployee::create($employee);
            }
        }
    }
}
