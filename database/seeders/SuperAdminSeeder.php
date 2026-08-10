<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superAdmin = User::updateOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name' => 'Super Admin',
                'email' => 'superadmin@example.com',
                'password' => Hash::make('password123'), // Change this in production!
                'phone' => '+1234567890',
                'address' => 'Admin Headquarters',
                'roles' => json_encode(['super_admin']),
                'acc_status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        $directorPosition = Position::firstOrCreate(
            ['title' => 'Director'],
            [
                'salary' => 150000,
                'status' => 'active',
                'is_sys_user' => true,
                'description' => 'Executive leadership position',
            ],
        );

        Employee::updateOrCreate(
            ['user_id' => $superAdmin->id],
            [
                'name' => $superAdmin->name,
                'father_name' => 'N/A',
                'contact' => $superAdmin->phone,
                'addr' => $superAdmin->address,
                'education_lvl' => 'Bachelor',
                'position_id' => $directorPosition->id,
                'salary' => $directorPosition->salary,
                'bonus' => false,
                'department' => 'Administration',
                'join_date' => now()->toDateString(),
                'contract_duration' => 'Permanent',
            ],
        );

        $this->command->info('Super Admin user and employee record created successfully!');
    }
}
