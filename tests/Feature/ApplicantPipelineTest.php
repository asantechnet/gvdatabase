<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ApplicantStage;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApplicantPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'super_admin']));
        Sanctum::actingAs($user, ['access']);
    }

    public function test_list_supports_search_filters_and_boards(): void
    {
        $iran = Country::create(['name' => 'Iran', 'code' => 'IRN', 'is_active' => true]);
        $turkey = Country::create(['name' => 'Turkey', 'code' => 'TUR', 'is_active' => true]);
        $employee = Employee::factory()->create();
        $ahmad = Customer::factory()->create(['name' => 'Ahmad Ahmadi']);
        $sara = Customer::factory()->create(['name' => 'Sara Mohammadi']);

        $a = $this->postJson('/api/applicants', ['customer_id' => $ahmad->id, 'country_id' => $iran->id, 'responsible_employee_id' => $employee->id])->assertCreated()->json('data');
        $b = $this->postJson('/api/applicants', ['customer_id' => $sara->id, 'country_id' => $turkey->id])->assertCreated()->json('data');

        // Search by customer name.
        $this->getJson('/api/applicants?search=Ahmad')->assertOk()->assertJsonPath('data.total', 1)->assertJsonFragment(['id' => $a['id']]);

        // Filter by country and employee.
        $this->getJson("/api/applicants?country_id={$iran->id}")->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson("/api/applicants?responsible_employee_id={$employee->id}")->assertOk()->assertJsonPath('data.total', 1);

        // Operation board holds both active cases.
        $this->getJson('/api/applicants?board=operation')->assertOk()->assertJsonPath('data.total', 2);

        // Move one case to the completed stage directly in storage (terminal
        // stages are not reachable through linear transitions by design).
        $completed = ApplicantStage::query()->where('key', 'completed')->firstOrFail();
        Applicant::query()->whereKey($b['id'])->update(['current_stage_id' => $completed->id, 'status' => 'closed']);

        $this->getJson('/api/applicants?board=operation')->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson('/api/applicants?board=final')->assertOk()->assertJsonPath('data.total', 1)->assertJsonFragment(['id' => $b['id']]);
        $this->getJson('/api/applicants?status=closed')->assertOk()->assertJsonPath('data.total', 1);
    }

    public function test_pipeline_counts_respect_active_filters(): void
    {
        $iran = Country::create(['name' => 'Iran', 'code' => 'IRN', 'is_active' => true]);
        $turkey = Country::create(['name' => 'Turkey', 'code' => 'TUR', 'is_active' => true]);

        $this->postJson('/api/applicants', ['customer_id' => Customer::factory()->create()->id, 'country_id' => $iran->id])->assertCreated();
        $this->postJson('/api/applicants', ['customer_id' => Customer::factory()->create()->id, 'country_id' => $turkey->id])->assertCreated();

        $all = $this->getJson('/api/applicants-pipeline')->assertOk()->json('data');
        $this->assertSame(2, $all['totals']['all']);
        $this->assertSame(2, $all['totals']['active']);
        $this->assertSame(array_sum(array_column($all['stages'], 'count')), $all['totals']['all']);

        $filtered = $this->getJson("/api/applicants-pipeline?country_id={$iran->id}")->assertOk()->json('data');
        $this->assertSame(1, $filtered['totals']['all']);
        $this->assertSame(1, array_sum(array_column($filtered['stages'], 'count')));
        $this->assertNotEmpty($filtered['stages']);
        $this->assertArrayHasKey('key', $filtered['stages'][0]);
        $this->assertArrayHasKey('allowed_next_keys', $filtered['stages'][0]);
    }

    public function test_pipeline_and_list_require_view_permission(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['access']);
        $this->getJson('/api/applicants')->assertForbidden();
        $this->getJson('/api/applicants-pipeline')->assertForbidden();
    }

    public function test_illegal_stage_jump_is_rejected(): void
    {
        $customer = Customer::factory()->create();
        $a = $this->postJson('/api/applicants', ['customer_id' => $customer->id])->assertCreated()->json('data');
        $completed = ApplicantStage::query()->where('key', 'completed')->firstOrFail();

        $this->postJson("/api/applicants/{$a['id']}/change-stage", ['stage_id' => $completed->id])->assertUnprocessable();
    }
}
