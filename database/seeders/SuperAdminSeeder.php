<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Position;
use App\Models\Role;
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
        $email = (string) env('SUPER_ADMIN_EMAIL', '');
        $password = (string) env('SUPER_ADMIN_PASSWORD', '');
        if ($email === '' || $password === '') {
            throw new \RuntimeException('SUPER_ADMIN_EMAIL and SUPER_ADMIN_PASSWORD are required to provision an administrator.');
        }

        $superAdmin = User::firstOrNew(['email' => $email]);
        $superAdmin->fill([
            'name' => (string) env('SUPER_ADMIN_NAME', 'Super Admin'),
            'phone' => (string) env('SUPER_ADMIN_PHONE', ''),
            'address' => (string) env('SUPER_ADMIN_ADDRESS', ''),
            'acc_status' => 'active',
        ]);
        if (! $superAdmin->exists) {
            $superAdmin->password = Hash::make($password);
            $superAdmin->email_verified_at = now();
        }
        $superAdmin->save();

        $directorPosition = Position::firstOrCreate(
            ['title' => 'Director'],
            [
                'salary' => 150000,
                'status' => 'active',
                'description' => 'Executive leadership position',
            ],
        );

        $superAdmin->positions()->syncWithoutDetaching([
            $directorPosition->id => ['assigned_at' => now()->toDateString()],
        ]);

        // Account access roles are assigned through the user_roles pivot table.
        $superAdminRole = Role::query()->where('name', 'super_admin')->first();
        if ($superAdminRole) {
            $superAdmin->roles()->syncWithoutDetaching([$superAdminRole->id]);
        }

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
                'department' => 'Administration',
                'join_date' => now()->toDateString(),
                'contract_duration' => 'Permanent',
            ],
        );

        $this->command->info('Super Admin user, role, and employee position created successfully!');
    }
}
