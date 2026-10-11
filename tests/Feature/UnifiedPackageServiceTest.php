<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ApplicantStage;
use App\Models\CaseWin;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UnifiedPackageServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogue_packages_can_no_longer_issue_contracts(): void
    {
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
        $this->postJson('/api/packages/1/issue', [])->assertNotFound();
        $this->assertFalse(Schema::hasTable('customer_packages'));
        $this->assertDatabaseCount('applicant_contract', 0);
    }

    public function test_a_case_win_is_snapshotted_once_and_not_duplicated_at_completion(): void
    {
        $admin = User::factory()->create(['acc_status' => 'active']);
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'super_admin']));
        Sanctum::actingAs($admin, ['access']);
        $worker = User::factory()->create(['acc_status' => 'active']);
        $employee = Employee::factory()->create(['user_id' => $worker->id]);
        $processing = ApplicantStage::where('key', 'visa_processing')->firstOrFail();
        $approved = ApplicantStage::where('key', 'visa_approved')->firstOrFail();
        $completed = ApplicantStage::where('key', 'completed')->firstOrFail();
        $applicant = Applicant::create([
            'name' => 'Visa Case',
            'responsible_employee_id' => $employee->id,
            'current_stage_id' => $processing->id,
            'status' => 'active',
        ]);

        $this->postJson("/api/applicants/{$applicant->id}/change-stage", ['stage_id' => $approved->id])->assertOk();
        $win = CaseWin::firstOrFail();
        $this->assertSame($employee->id, $win->employee_id);
        $this->assertSame('visa_approved', $win->stage_key);
        $this->assertDatabaseHas('notifications', ['notification_key' => "case.won.{$win->id}"]);

        $this->postJson("/api/applicants/{$applicant->id}/change-stage", ['stage_id' => $completed->id])->assertOk();
        $this->assertSame(1, CaseWin::count());
    }
}
