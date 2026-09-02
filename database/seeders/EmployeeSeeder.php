<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $mockData = [
            [
                'name' => 'John Doe',
                'father_name' => 'Michael Doe',
                'contact' => '555-1234',
                'education_lvl' => 'Bachelor',
                'email' => 'john.doe@example.com',
                'addr' => '123 Main St, Cityville',
                'position_id' => 1,
                'salary' => 5000,
                'join_date' => '2022-01-15',
                'contract_duration' => '2 years',
            ],
            [
                'name' => 'Jane Smith',
                'father_name' => 'Robert Smith',
                'contact' => '555-5678',
                'education_lvl' => 'Master',
                'email' => 'jane.smith@example.com',
                'addr' => '456 Oak Ave, Townsville',
                'position_id' => 1,
                'salary' => 6500,
                'join_date' => '2021-11-01',
                'contract_duration' => '3 years',
            ],
            [
                'name' => 'David Johnson',
                'father_name' => 'William Johnson',
                'contact' => '555-9012',
                'education_lvl' => 'High School',
                'email' => 'david.johnson@example.com',
                'addr' => '789 Pine Rd, Villagetown',
                'position_id' => 1,
                'salary' => 3500,
                'join_date' => '2023-02-20',
                'contract_duration' => '1 year',
            ],
        ];

        foreach ($mockData as $staffMember) {
            $user = User::firstOrCreate(
                ['email' => $staffMember['email']],
                [
                    'name' => $staffMember['name'],
                    'password' => Hash::make(Str::random(64)),
                    'phone' => $staffMember['contact'],
                    'address' => $staffMember['addr'],
                    'acc_status' => 'active',
                ],
            );
            Employee::updateOrCreate(
                ['user_id' => $user->id],
                [
                    ...collect($staffMember)->except('email')->all(),
                    'user_id' => $user->id,
                ],
            );
        }
    }
}
