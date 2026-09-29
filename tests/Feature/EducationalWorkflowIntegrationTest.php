<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ApplicantStage;
use App\Models\ApplicantStageHistory;
use App\Models\Currency;
use App\Models\Country;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\PendingTransaction;
use App\Models\Role;
use App\Models\User;
use App\Models\University;
use App\Services\EducationalWorkflowNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EducationalWorkflowIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_stage_and_stalled_case_notifications_are_delivered_once(): void
    {
        $manager = $this->userWithRole('manager');
        $agent = $this->userWithRole('staff');
        $employee = Employee::factory()->create(['user_id' => $agent->id]);
        Sanctum::actingAs($manager, ['access']);

        $case = $this->postJson('/api/applicants', [
            'name' => 'Case 1',
            'responsible_employee_id' => $employee->id,
        ])->assertCreated()->json('data');

        $this->assertSame(1, $agent->notifications()->where('notification_key', "applicant.assigned.{$case['id']}.{$agent->id}")->count());

        $next = ApplicantStage::query()->where('key', 'consultation')->firstOrFail();
        $this->postJson("/api/applicants/{$case['id']}/change-stage", ['stage_id' => $next->id])
            ->assertOk();
        $this->assertSame(1, $agent->notifications()->where('notification_key', 'like', 'applicant.stage.%.staff')->count());

        $applicant = Applicant::query()->findOrFail($case['id']);
        ApplicantStageHistory::query()->where('applicant_id', $applicant->id)
            ->update(['changed_at' => CarbonImmutable::parse('2026-08-01')]);
        $service = app(EducationalWorkflowNotificationService::class);

        $this->assertSame(1, $service->sendStalledCaseReminders(CarbonImmutable::parse('2026-08-16')));
        $this->assertSame(0, $service->sendStalledCaseReminders(CarbonImmutable::parse('2026-08-16')));
        $this->assertSame(1, Notification::query()->where('notification_key', 'like', "applicant.stalled.{$applicant->id}.%")->count());
    }

    public function test_financial_requests_notify_reviewers_and_staff_only_see_their_own_requests(): void
    {
        $manager = $this->userWithRole('manager');
        $creator = $this->userWithRole('staff');
        $other = $this->userWithRole('staff');
        Employee::factory()->create(['user_id' => $creator->id]);
        Employee::factory()->create(['user_id' => $other->id]);
        $currency = Currency::factory()->create(['is_base' => true, 'rate' => 1]);

        Sanctum::actingAs($creator, ['access']);
        $pending = $this->postJson('/api/pending-transactions', [
            'transaction_type' => 'income',
            'reason' => 'Applicant deposit',
            'amount' => 25000,
            'currency_id' => $currency->id,
        ])->assertCreated()->json('data');

        PendingTransaction::create([
            'transaction_type' => 'income',
            'reason' => 'Another agent request',
            'amount' => 100,
            'currency_id' => $currency->id,
            'requested_by' => $other->id,
            'approval_status' => PendingTransaction::SUBMITTED,
            'requested_at' => now(),
        ]);

        $this->getJson('/api/pending-transactions')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $pending['id']);
        $this->assertSame(1, $manager->notifications()->where('notification_key', "finance.pending.submitted.{$pending['id']}")->count());

        Sanctum::actingAs($manager, ['access']);
        $this->postJson("/api/pending-transactions/{$pending['id']}/approve")->assertOk();
        $this->assertSame(1, $creator->notifications()->where('notification_key', "finance.pending.approved.{$pending['id']}")->count());
    }

    public function test_staff_cannot_assign_cases_or_sales_to_another_employee(): void
    {
        $agent = $this->userWithRole('staff');
        $ownEmployee = Employee::factory()->create(['user_id' => $agent->id]);
        $otherEmployee = Employee::factory()->create();
        $currency = Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        Sanctum::actingAs($agent, ['access']);

        $this->postJson('/api/applicants', [
            'name' => 'Spoofed Case',
            'responsible_employee_id' => $otherEmployee->id,
        ])->assertForbidden();

        $case = $this->postJson('/api/applicants', ['name' => 'Agent Case'])
            ->assertCreated()
            ->assertJsonPath('data.responsible_employee.id', $ownEmployee->id)
            ->json('data');
        $this->seedApplicantDocuments($case['id']);

        $payload = [
            'applicant_id' => $case['id'],
            'employee_id' => $otherEmployee->id,
            'currency_id' => $currency->id,
            'date' => now()->toDateString(),
            'fee' => 100,
        ];
        $this->postJson('/api/educational-services/test-offers', $payload)->assertForbidden();
        $this->postJson('/api/educational-services/test-offers', [...$payload, 'employee_id' => $ownEmployee->id])->assertCreated();
    }

    public function test_staff_dashboard_and_university_actions_are_limited_to_assigned_cases(): void
    {
        $manager = $this->userWithRole('manager');
        $agent = $this->userWithRole('staff');
        $otherAgent = $this->userWithRole('staff');
        $ownEmployee = Employee::factory()->create(['user_id' => $agent->id]);
        $otherEmployee = Employee::factory()->create(['user_id' => $otherAgent->id]);
        $country = Country::create(['name' => 'Germany', 'code' => 'DEU', 'is_active' => true]);
        $university = University::create(['name' => 'Example University', 'country_id' => $country->id, 'is_active' => true]);

        Sanctum::actingAs($manager, ['access']);
        $ownCase = $this->postJson('/api/applicants', [
            'name' => 'Case 2',
            'responsible_employee_id' => $ownEmployee->id,
        ])->assertCreated()->json('data.id');
        $otherCase = $this->postJson('/api/applicants', [
            'name' => 'Case 3',
            'responsible_employee_id' => $otherEmployee->id,
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($agent, ['access']);
        $this->getJson('/api/educational-dashboard?responsible_employee_id='.$otherEmployee->id)
            ->assertOk()
            ->assertJsonPath('data.pipeline.totals.all', 1)
            ->assertJsonCount(1, 'data.staff')
            ->assertJsonPath('data.staff.0.id', $ownEmployee->id);
        $this->postJson("/api/applicants/{$otherCase}/universities", ['university_id' => $university->id])->assertForbidden();
        $this->postJson("/api/applicants/{$ownCase}/universities", ['university_id' => $university->id])->assertCreated();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['acc_status' => 'active']);
        $user->roles()->attach(Role::query()->where('name', $role)->firstOrFail());

        return $user;
    }
}
