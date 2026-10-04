<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ApplicantStage;
use App\Models\CaseWin;
use App\Models\Currency;
use App\Models\CustomerPackage;
use App\Models\Employee;
use App\Models\Package;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Support\AuditRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UnifiedPackageServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_package_is_a_one_time_bundle_of_unified_services(): void
    {
        $admin = User::factory()->create(['acc_status' => 'active']);
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'super_admin']));
        Sanctum::actingAs($admin, ['access']);

        $currency = Currency::factory()->create(['is_base' => true, 'is_active' => true, 'rate' => 1]);
        $scholarship = Service::factory()->create(['type' => 'Scholarship Assistance', 'category' => 'scholarship', 'currency_id' => $currency->id, 'fee' => 600]);
        $testOffer = Service::factory()->create(['type' => 'Language Test Offer', 'category' => 'test_offer', 'currency_id' => $currency->id, 'fee' => 400]);
        $package = $this->postJson('/api/packages', [
            'name' => 'Student Success Bundle', 'description' => 'One-time scholarship and test support',
            'service_ids' => [$scholarship->id, $testOffer->id], 'currency_id' => $currency->id, 'fee' => 900,
        ])->assertCreated()->assertJsonCount(2, 'data.services')->json('data');

        $employee = Employee::factory()->create();
        $applicant = Applicant::factory()->create(['responsible_employee_id' => $employee->id]);
        // Package issuance generates contracts, which require at least one
        // document on the applicant case.
        $this->seedApplicantDocuments($applicant);
        $payload = ['applicant_id' => $applicant->id, 'sold_by_employee_id' => $employee->id,
            'currency_id' => $currency->id, 'fee' => 900, 'purchased_at' => '2026-09-20'];

        $purchase = $this->postJson("/api/packages/{$package['id']}/issue", $payload)
            ->assertCreated()->assertJsonCount(2, 'data.services')->json('data');
        $this->assertDatabaseCount('customer_packages', 1);
        $this->assertDatabaseCount('contract_service', 2);
        $this->assertEqualsWithDelta(900, (float) collect($purchase['services'])->sum('fee'), .001);

        $this->postJson("/api/packages/{$package['id']}/issue", $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('package_id');
        $this->assertDatabaseCount('contract_service', 2);

        $this->getJson('/api/educational-services/scholarships')->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson('/api/educational-services/test-offers')->assertOk()->assertJsonPath('data.total', 1);
        $this->assertSame('packages', AuditRegistry::keyFor(Package::class));
        $this->assertSame('customer_packages', AuditRegistry::keyFor(CustomerPackage::class));
        $this->assertSame('case_wins', AuditRegistry::keyFor(CaseWin::class));
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
            'name' => 'Package Case',
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
