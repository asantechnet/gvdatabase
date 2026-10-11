<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\WorthyEmployee;
use Illuminate\Database\Seeder;

class WorthyEmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::query()->where('status', Employee::STATUS_ACTIVE)->orderBy('id')->get();
        if ($employees->isEmpty()) {
            return;
        }

        $currentMonth = now()->format('Y-m-01');
        $lastMonth = now()->subMonth()->format('Y-m-01');

        $nominations = [
            ['system_key' => 'counselor', 'score' => 4.9, 'type' => 'Performance', 'month' => $currentMonth, 'granted' => false,
                'details' => 'Closed the highest number of scholarship cases this quarter with flawless documentation.'],
            ['system_key' => 'admission', 'score' => 4.8, 'type' => 'Task Completion', 'month' => $lastMonth, 'granted' => true,
                'details' => 'Processed a full intake wave with zero rejected submissions.'],
            ['system_key' => 'finance_officer', 'score' => 4.7, 'type' => 'Manager Recognition', 'month' => $lastMonth, 'granted' => true,
                'details' => 'Reconciled the month\'s receipts and salary ledger with no discrepancies.'],
        ];

        foreach ($nominations as $nomination) {
            $employee = $employees->first(fn (Employee $e) => $e->position?->system_key === $nomination['system_key']);
            if (! $employee) {
                continue;
            }

            WorthyEmployee::updateOrCreate(
                ['employee_id' => $employee->id, 'selected_type' => $nomination['type']],
                [
                    'bonus_month' => $nomination['month'],
                    'performance_score' => $nomination['score'],
                    'achievement_details' => $nomination['details'],
                    'suggested_amount' => 3000,
                    'selected_amount' => $nomination['granted'] ? 3000 : 0,
                    'selected_type' => $nomination['type'],
                    'is_granted' => $nomination['granted'],
                    'granted_amount' => $nomination['granted'] ? 3000 : 0,
                    'granted_at' => $nomination['granted'] ? now() : null,
                ],
            );
        }
    }
}
