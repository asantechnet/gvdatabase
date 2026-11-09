<?php

namespace Database\Seeders;

use App\Models\Position;
use Illuminate\Database\Seeder;

class PositionSeeder extends Seeder
{
    /**
     * Positions describe the work a person does; roles (seeded separately)
     * describe their authority. `system_key` is the stable machine identifier
     * used by specialist pickers such as AgencyStaffAssignment.
     */
    public function run(): void
    {
        $positions = [
            ['title' => 'Director', 'system_key' => 'director', 'salary' => 150000, 'description' => 'Executive leadership'],
            ['title' => 'Operations Manager', 'system_key' => 'manager', 'salary' => 120000, 'description' => 'Runs day-to-day operations and supervises teams'],
            ['title' => 'Senior Counselor', 'system_key' => 'senior_counselor', 'salary' => 80000, 'description' => 'Leads a counseling team and reviews cases'],
            ['title' => 'Counselor', 'system_key' => 'counselor', 'salary' => 60000, 'description' => 'Advises applicants and owns their cases'],
            ['title' => 'Admission Officer', 'system_key' => 'admission', 'salary' => 55000, 'description' => 'Processes admissions and intake'],
            ['title' => 'Case Officer', 'system_key' => 'case_officer', 'salary' => 50000, 'description' => 'Tracks case progress and follow-ups'],
            ['title' => 'Finance Officer', 'system_key' => 'finance_officer', 'salary' => 70000, 'description' => 'Handles receipts, salaries and financial records'],
            ['title' => 'Document Officer', 'system_key' => 'document_officer', 'salary' => 45000, 'description' => 'Collects and verifies applicant documents'],
            ['title' => 'Teacher', 'system_key' => 'teacher', 'salary' => 50000, 'description' => 'Teaches Academy classes and records attendance'],
        ];

        foreach ($positions as $position) {
            Position::updateOrCreate(
                ['system_key' => $position['system_key']],
                [
                    'title' => $position['title'],
                    'salary' => $position['salary'],
                    'status' => 'active',
                    'description' => $position['description'],
                ],
            );
        }
    }
}
