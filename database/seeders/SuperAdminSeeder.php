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
     * Demo administrator credentials.
     *
     * Deliberately hardcoded so seeding never depends on environment
     * configuration. These are demo-only credentials and must only be run
     * against a non-production database.
     */
    public const DEMO_EMAIL = 'admin@example.test';

    public const DEMO_PASSWORD = '1234';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superAdmin = User::withTrashed()->firstOrNew(['email' => self::DEMO_EMAIL]);
        $superAdmin->fill([
            'name' => 'Demo Admin',
            'phone' => '+93 700 000 000',
            'address' => 'Kabul, Afghanistan',
            'acc_status' => 'active',
            'email_verified_at' => now(),
            'must_set_password' => false,
        ]);
        $superAdmin->password = Hash::make(self::DEMO_PASSWORD);
        $superAdmin->deleted_at = null;
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

        $this->command->info(sprintf(
            'Demo admin %s seeded with password "%s".',
            self::DEMO_EMAIL,
            self::DEMO_PASSWORD,
        ));
    }
}
