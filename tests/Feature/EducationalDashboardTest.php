<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\Applicant;
use App\Models\ApplicantContract;
use App\Models\ApplicantStage;
use App\Models\ApplicantUniversity;
use App\Models\Country;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Permission;
use App\Models\University;
use App\Models\Visitor;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesBuiltInContracts;
use Tests\TestCase;

class EducationalDashboardTest extends TestCase
{
    use CreatesBuiltInContracts, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Employee::factory()->create(); // The primary system employee is excluded from staff reports.
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'super_admin']));
        Sanctum::actingAs($user, ['access']);
    }

    public function test_overview_reports_pipeline_sales_staff_and_attention(): void
    {
        $iran = Country::create(['name' => 'Iran', 'code' => 'IRN', 'is_active' => true]);
        $employee = Employee::factory()->create();
        $contract = $this->createBuiltInContract('scholarship', ['name' => 'Overview Case', 'country_id' => $iran->id, 'responsible_employee_id' => $employee->id, 'fee' => 500]);
        $a = ['id' => $contract['applicant_id']];

        $board = Board::factory()->create();
        Task::create(['board_id' => $board->id, 'applicant_id' => $a['id'], 'title' => 'Overdue case work', 'priority' => 'high', 'progress' => 0, 'due_date' => now()->subDay()->toDateString(), 'order' => 0]);

        // The case has sat untouched for 20 days, so it counts as stuck.
        DB::table('applicant_stage_histories')->where('applicant_id', $a['id'])->update(['changed_at' => now()->subDays(20)]);
        DB::table('applicants')->where('id', $a['id'])->update(['created_at' => now()->subDays(20)]);

        $data = $this->getJson('/api/educational-dashboard')->assertOk()->json('data');

        // Pipeline totals reconcile with the column counts.
        $this->assertSame($data['pipeline']['totals']['all'], array_sum(array_column($data['pipeline']['stages'], 'count')));
        $this->assertSame(1, $data['pipeline']['totals']['active']);

        // Sales and staff attribution.
        $this->assertSame(1, $data['sales']['scholarships']['sold']);
        $staff = collect($data['staff'])->firstWhere('id', $employee->id);
        $this->assertSame(1, $staff['active_applicants']);

        // Attention surfaces the overdue task and the idle case.
        $this->assertNotEmpty($data['attention']['overdue_tasks']);
        $this->assertNotEmpty($data['attention']['stuck_applicants']);
        $this->assertSame($iran->name, $data['countries'][0]['name']);
    }

    public function test_pipeline_counts_each_service_line_in_its_own_column(): void
    {
        $this->createBuiltInContract('scholarship', ['name' => 'Visa Case', 'fee' => 300]);
        $this->createBuiltInContract('test_offer', ['name' => 'Offer Case', 'fee' => 100]);

        $data = $this->getJson('/api/educational-dashboard')->assertOk()->json('data');
        $counted = collect($data['pipeline']['stages'])->where('count', '>', 0);

        $this->assertSame(1, $counted->where('service', 'scholarship')->sum('count'));
        $this->assertSame(1, $counted->where('service', 'test_offer')->sum('count'));
        $this->assertSame(2, $data['pipeline']['totals']['all']);

        // Moving one line only changes that service's column.
        $line = ApplicantContract::query()->where('service', 'test_offer')->firstOrFail();
        $next = ApplicantStage::query()->where('service', 'test_offer')->where('key', 'documents_required')->firstOrFail();
        $this->postJson("/api/applicant-contracts/{$line->id}/change-stage", ['stage_id' => $next->id])->assertOk();

        $moved = collect($this->getJson('/api/educational-dashboard')->assertOk()->json('data.pipeline.stages'));
        $column = fn (string $service, string $key) => $moved->first(fn ($row) => $row['service'] === $service && $row['key'] === $key)['count'];
        $this->assertSame(1, $column('test_offer', 'documents_required'));
        $this->assertSame(0, $column('test_offer', 'new'));
        $this->assertSame(1, $column('scholarship', 'new'));
    }

    public function test_overview_respects_filters_and_permissions(): void
    {
        $iran = Country::create(['name' => 'Iran', 'code' => 'IRN', 'is_active' => true]);
        $turkey = Country::create(['name' => 'Turkey', 'code' => 'TUR', 'is_active' => true]);
        $this->postJson('/api/applicants', ['name' => 'Case 1', 'country_id' => $iran->id])->assertCreated();
        $this->postJson('/api/applicants', ['name' => 'Case 2', 'country_id' => $turkey->id])->assertCreated();

        $filtered = $this->getJson("/api/educational-dashboard?country_id={$iran->id}")->assertOk()->json('data');
        $this->assertSame(1, $filtered['pipeline']['totals']['all']);

        Sanctum::actingAs(User::factory()->create(), ['access']);
        $this->getJson('/api/educational-dashboard')->assertForbidden();
    }

    public function test_priority_counts_are_not_capped_and_new_cases_are_not_idle(): void
    {
        Applicant::factory()->count(12)->create(['status' => 'active', 'created_at' => now()->subDays(20)]);
        Applicant::factory()->create(['status' => 'active']);

        $data = $this->getJson('/api/educational-dashboard?date_from='.now()->toDateString())->assertOk()->json('data');
        $this->assertSame(12, $data['attention']['counts']['stuck_applicants']);
        $this->assertCount(10, $data['attention']['stuck_applicants']);
        $this->assertSame(13, $data['operations']['active_cases']);
        $this->assertSame(1, $data['pipeline']['totals']['all']);
    }

    public function test_balances_use_discounts_and_payment_overrides_and_exclude_cancelled_contracts(): void
    {
        $sale = $this->createBuiltInContract('scholarship', ['fee' => 500, 'discount' => 50]);
        $line = ApplicantContract::where('contract_id', $sale['id'])->firstOrFail();
        $line->update(['amount_paid' => 100, 'amount_paid_overridden' => true]);
        $cancelled = $this->createBuiltInContract('academy', ['fee' => 700]);
        DB::table('contracts')->where('id', $cancelled['id'])->update(['status' => 'cancelled', 'is_cancelled' => true]);

        $university = University::create(['name' => 'Dashboard University', 'is_active' => true]);
        ApplicantUniversity::create(['applicant_id' => $sale['applicant_id'], 'university_id' => $university->id, 'status' => 'admitted']);
        $data = $this->getJson('/api/educational-dashboard')->assertOk()->json('data');
        $this->assertCount(1, $data['operations']['outstanding']);
        $this->assertEquals(350, $data['operations']['outstanding'][0]['amount']);
        $this->assertSame(1, $data['operations']['admissions']['admitted']);
        $this->assertSame(0, $data['sales']['academy']['sold']);
        $this->getJson('/api/applicants?focus=outstanding')->assertOk()
            ->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.id', $sale['applicant_id']);
        $this->getJson('/api/applicants?focus=admitted')->assertOk()
            ->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.id', $sale['applicant_id']);
        $this->getJson('/api/applicants?focus=awaiting_decision')->assertOk()->assertJsonPath('data.total', 0);
    }

    public function test_overview_permission_without_an_employee_does_not_expose_the_agency(): void
    {
        Applicant::factory()->create(['status' => 'active']);
        $user = User::factory()->create();
        $role = Role::create(['name' => 'overview_only']);
        $role->permissions()->attach(Permission::firstOrCreate(['name' => 'view_agency_overview'], ['category' => 'educational_visa']));
        $user->roles()->attach($role);
        Sanctum::actingAs($user, ['access']);

        $this->getJson('/api/educational-dashboard')->assertOk()
            ->assertJsonPath('data.pipeline.totals.all', 0);
    }

    public function test_current_prospects_and_contract_expiry_are_actionable_and_destination_scoped(): void
    {
        Visitor::create(['name' => 'Open prospect', 'contact' => '070000001', 'date' => now()->toDateString(), 'service' => 'scholarship']);
        Visitor::create(['name' => 'Converted prospect', 'contact' => '070000002', 'date' => now()->toDateString(), 'service' => 'scholarship', 'converted_at' => now()]);
        $country = Country::create(['name' => 'Destination', 'code' => 'DS', 'is_active' => true]);
        $sale = $this->createBuiltInContract('scholarship', ['country_id' => $country->id]);
        DB::table('contracts')->where('id', $sale['id'])->update(['status' => 'active', 'end_date' => now()->addDays(10)->toDateString()]);
        $data = $this->getJson('/api/educational-dashboard')->assertOk()->json('data');
        $this->assertSame(1, $data['operations']['prospects']);
        $this->assertSame(1, $data['attention']['counts']['contracts_requiring_attention']);
        $this->assertSame('applicant-case', $data['attention']['contracts_requiring_attention'][0]['route']['name']);
        $this->getJson('/api/educational-dashboard?country_id='.$country->id)->assertOk()->assertJsonPath('data.operations.prospects', null);
    }
}
