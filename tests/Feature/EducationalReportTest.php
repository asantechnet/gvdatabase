<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ApplicantStage;
use App\Models\Country;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesBuiltInContracts;
use Tests\TestCase;

class EducationalReportTest extends TestCase
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

    public function test_applicant_report_lists_cases_with_filters(): void
    {
        $iran = Country::create(['name' => 'Iran', 'code' => 'IRN', 'is_active' => true]);
        $turkey = Country::create(['name' => 'Turkey', 'code' => 'TUR', 'is_active' => true]);
        $employee = Employee::factory()->create();
        $this->postJson('/api/applicants', ['name' => 'Case 1', 'country_id' => $iran->id, 'responsible_employee_id' => $employee->id])->assertCreated();
        $this->postJson('/api/applicants', ['name' => 'Case 2', 'country_id' => $turkey->id])->assertCreated();

        $report = $this->getJson('/api/educational-reports/applicants')->assertOk()->json('data');
        $this->assertSame(2, $report['total']);
        $this->assertContains('stage', $report['columns']);

        $filtered = $this->getJson("/api/educational-reports/applicants?country_id={$iran->id}")->assertOk()->json('data');
        $this->assertSame(1, $filtered['total']);
        $this->assertSame('Iran', $filtered['rows'][0]['country']);
    }

    public function test_performance_and_sales_reports_attribute_work(): void
    {
        $employee = Employee::factory()->create();
        $employee->update(['user_id' => auth()->id()]);
        $contract = $this->createBuiltInContract('scholarship', ['name' => 'Sales Case', 'responsible_employee_id' => $employee->id, 'fee' => 750]);

        $performance = $this->getJson('/api/educational-reports/employee-performance')->assertOk()->json('data');
        $row = collect($performance['rows'])->firstWhere('employee', $employee->name);
        $this->assertSame(1, $row['active_cases']);
        $this->assertSame(1, $row['services_sold']);
        $this->assertSame(1, $row['contracts_sold']);
        $this->assertSame(0.0, (float) $row['bonus_amount']);

        $sales = $this->getJson('/api/educational-reports/services')->assertOk()->json('data');
        $this->assertSame(1, $sales['total']);
        $this->assertSame('scholarship', $sales['rows'][0]['service']);
    }

    public function test_visa_outcomes_buckets_by_country(): void
    {
        $iran = Country::create(['name' => 'Iran', 'code' => 'IRN', 'is_active' => true]);
        $a = $this->postJson('/api/applicants', ['name' => 'Case 3', 'country_id' => $iran->id])->assertCreated()->json('data');
        $completed = ApplicantStage::query()->where('key', 'completed')->firstOrFail();
        Applicant::query()->whereKey($a['id'])->update(['current_stage_id' => $completed->id, 'status' => 'closed']);

        $report = $this->getJson('/api/educational-reports/visa-outcomes')->assertOk()->json('data');
        $row = collect($report['rows'])->firstWhere('country', 'Iran');
        $this->assertSame(1, $row['completed']);
        $this->assertSame(1, $row['total']);
        $this->assertSame(100.0, (float) $row['success_rate_pct']);
    }

    public function test_export_downloads_csv_and_permissions_hold(): void
    {
        $this->postJson('/api/applicants', ['name' => 'Case 4'])->assertCreated();

        $export = $this->getJson('/api/educational-reports/applicants/export')->assertOk();
        $this->assertSame('text/csv; charset=UTF-8', $export->headers->get('Content-Type'));
        $this->assertStringStartsWith("\xEF\xBB\xBF", $export->streamedContent() ?? $export->getContent());
        $this->assertStringContainsString('applicant', $export->streamedContent() ?? $export->getContent());

        Sanctum::actingAs(User::factory()->create(), ['access']);
        $this->getJson('/api/educational-reports/applicants')->assertForbidden();
    }
}
