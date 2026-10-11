<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Office;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class EmployeeSeeder extends Seeder
{
    /**
     * The demo workforce.
     *
     * Every person maps to a real position (with its `system_key`) and an
     * access role, so the specialist pickers (`AgencyStaffAssignment`), the
     * counselor auto-select, and the task assignment rules all work on seeded
     * data instead of depending on factory randomness. `supervisor` is the
     * email of the person they report to.
     *
     * @var list<array<string, mixed>>
     */
    private const STAFF = [
        [
            'name' => 'Zahra Aziz', 'email' => 'zahra.aziz@example.test', 'contact' => '+93 700 100 001',
            'position' => 'manager', 'role' => 'manager', 'department' => 'Operations',
            'education_lvl' => 'Master', 'salary' => 120000, 'join_date' => '2021-03-01',
            'contract_duration' => 'Permanent', 'supervisor' => null,
        ],
        [
            'name' => 'Fatima Noori', 'email' => 'fatima.noori@example.test', 'contact' => '+93 700 100 002',
            'position' => 'senior_counselor', 'role' => 'senior_counselor', 'department' => 'Counseling',
            'education_lvl' => 'Master', 'salary' => 80000, 'join_date' => '2022-01-10',
            'contract_duration' => 'Permanent', 'supervisor' => 'zahra.aziz@example.test',
        ],
        [
            'name' => 'Ahmad Karimi', 'email' => 'ahmad.karimi@example.test', 'contact' => '+93 700 100 003',
            'position' => 'counselor', 'role' => 'counselor', 'department' => 'Counseling',
            'education_lvl' => 'Bachelor', 'salary' => 60000, 'join_date' => '2023-02-20',
            'contract_duration' => '2 years', 'supervisor' => 'fatima.noori@example.test',
        ],
        [
            'name' => 'Bilal Ahmadi', 'email' => 'bilal.ahmadi@example.test', 'contact' => '+93 700 100 004',
            'position' => 'admission', 'role' => 'staff', 'department' => 'Admissions',
            'education_lvl' => 'Bachelor', 'salary' => 55000, 'join_date' => '2023-05-02',
            'contract_duration' => '2 years', 'supervisor' => 'fatima.noori@example.test',
        ],
        [
            'name' => 'Sana Rahimi', 'email' => 'sana.rahimi@example.test', 'contact' => '+93 700 100 005',
            'position' => 'finance_officer', 'role' => 'finance_officer', 'department' => 'Finance',
            'education_lvl' => 'Bachelor', 'salary' => 70000, 'join_date' => '2022-09-15',
            'contract_duration' => 'Permanent', 'supervisor' => 'zahra.aziz@example.test',
        ],
        [
            'name' => 'Omid Safi', 'email' => 'omid.safi@example.test', 'contact' => '+93 700 100 006',
            'position' => 'document_officer', 'role' => 'document_officer', 'department' => 'Operations',
            'education_lvl' => 'Bachelor', 'salary' => 45000, 'join_date' => '2024-01-08',
            'contract_duration' => '1 year', 'supervisor' => 'zahra.aziz@example.test',
        ],
    ];

    public function run(): void
    {
        $officeId = Office::query()->where('code', 'HQ')->value('id');
        $positionIds = Position::query()->pluck('id', 'system_key');
        $roleIds = Role::query()->pluck('id', 'name');

        $employeesByEmail = [];

        foreach (self::STAFF as $member) {
            $user = User::firstOrNew(['email' => $member['email']]);
            $user->fill([
                'name' => $member['name'],
                'phone' => $member['contact'],
                'address' => 'Kabul, Afghanistan',
                'acc_status' => 'active',
                'email_verified_at' => $user->email_verified_at ?? now(),
                'must_set_password' => false,
            ]);
            if (! $user->exists) {
                // Documented demo credential for local environments.
                $user->password = Hash::make('password');
            }
            $user->save();

            $roleId = $roleIds[$member['role']] ?? null;
            if ($roleId) {
                $user->roles()->syncWithoutDetaching([$roleId]);
            }

            $employee = Employee::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'name' => $member['name'],
                    'father_name' => 'N/A',
                    'contact' => $member['contact'],
                    'addr' => 'Kabul, Afghanistan',
                    'education_lvl' => $member['education_lvl'],
                    'position_id' => $positionIds[$member['position']] ?? null,
                    'status' => Employee::STATUS_ACTIVE,
                    'office_id' => $officeId,
                    'salary' => $member['salary'],
                    'department' => $member['department'],
                    'join_date' => $member['join_date'],
                    'contract_duration' => $member['contract_duration'],
                ],
            );

            $employeesByEmail[$member['email']] = $employee;
        }

        // Second pass: wire the reporting line once every employee exists.
        foreach (self::STAFF as $member) {
            if (! $member['supervisor']) {
                continue;
            }
            $employee = $employeesByEmail[$member['email']] ?? null;
            $supervisor = $employeesByEmail[$member['supervisor']] ?? null;
            if ($employee && $supervisor && $employee->supervisor_id !== $supervisor->id) {
                $employee->update(['supervisor_id' => $supervisor->id]);
            }
        }
    }
}
