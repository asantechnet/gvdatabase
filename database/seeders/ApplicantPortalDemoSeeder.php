<?php

namespace Database\Seeders;

use App\Models\Applicant;
use App\Models\ApplicantStage;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Support\BuiltInService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ApplicantPortalDemoSeeder extends Seeder
{
    /**
     * Create an accepted applicant-portal invitation for local testing.
     *
     * This seeder is idempotent and deliberately uses a documented test
     * credential; it must only be run against a non-production database.
     */
    public function run(): void
    {
        $email = 'portal.applicant@example.test';
        $user = User::withTrashed()->firstOrNew(['email' => $email]);
        $user->fill([
            'name' => 'Portal Test Applicant',
            'phone' => '+93 700 000 001',
            'address' => 'Kabul, Afghanistan',
            'acc_status' => 'active',
            'email_verified_at' => now(),
            'must_set_password' => false,
        ]);
        $user->password = Hash::make('password');
        $user->deleted_at = null;
        $user->save();

        $applicantRole = Role::query()->where('name', 'applicant')->firstOrFail();
        $user->roles()->syncWithoutDetaching([$applicantRole->id]);

        $applicant = Applicant::withTrashed()->firstOrNew(['user_id' => $user->id]);
        $applicant->fill([
            'service' => BuiltInService::SCHOLARSHIP,
            'name' => $user->name,
            'contact' => $user->phone,
            'email' => $user->email,
            'address' => $user->address,
            'father_name' => 'Demo Guardian',
            'father_phone' => '+93 700 000 002',
            'responsible_employee_id' => Employee::query()->whereHas('position', fn ($query) => $query->where('system_key', 'counselor'))->value('id')
                ?? Employee::query()->orderBy('id')->value('id'),
            'admission_employee_id' => Employee::query()->whereHas('position', fn ($query) => $query->where('system_key', 'admission'))->value('id'),
            'current_stage_id' => ApplicantStage::query()->where('key', 'consultation')->value('id'),
            'status' => 'active',
            'notes' => 'Demo applicant portal case.',
            'profile_data' => [
                'nationality' => 'Afghan',
                'intended_intake' => 'Fall 2027',
                'preferred_contact_method' => 'email',
            ],
            'profile_completed_at' => now(),
        ]);
        $applicant->deleted_at = null;
        $applicant->save();

        $detail = $applicant->scholarshipDetail()->withTrashed()->firstOrCreate([]);
        if ($detail->trashed()) {
            $detail->restore();
        }
    }
}
