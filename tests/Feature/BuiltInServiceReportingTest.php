<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Employee;
use App\Models\User;
use App\Support\BuiltInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesBuiltInContracts;
use Tests\TestCase;

class BuiltInServiceReportingTest extends TestCase
{
    use CreatesBuiltInContracts, RefreshDatabase;

    public function test_all_builtin_services_are_reported_without_catalogue_rows_or_bonus_sources(): void
    {
        $user = User::factory()->create(['roles' => ['super_admin']]);
        Employee::factory()->create();
        $seller = Employee::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user, ['access']);
        $currency = Currency::factory()->create(['code' => 'USD', 'rate' => 1]);
        foreach (BuiltInService::ALL as $service) {
            $this->createBuiltInContract($service, [
                'name' => $service.' applicant', 'fee' => 100, 'currency_id' => $currency->id,
                'responsible_employee_id' => $seller->id,
            ]);
        }
        $this->assertFalse(Schema::hasTable('services'));
        $this->assertFalse(Schema::hasTable('packages'));
        $this->assertDatabaseCount('educational_bonus_sources', 0);
        $report = $this->getJson('/api/educational-reports/services')->assertOk()->assertJsonPath('data.total', count(BuiltInService::ALL))->json('data');
        $this->assertEqualsCanonicalizing(BuiltInService::ALL, array_column($report['rows'], 'service'));
        $this->assertNotContains('package', $report['columns']);
        $this->assertNotContains('package_type', array_keys($report['rows'][0]));
        $this->getJson('/api/educational-reports/services?package_id=1')->assertUnprocessable();
        $dashboard = $this->getJson('/api/educational-dashboard')->assertOk()->json('data');
        foreach (['scholarships', 'test_offers', 'academy', 'private'] as $key) {
            $this->assertSame(1, $dashboard['sales'][$key]['sold']);
            $this->assertSame('USD', $dashboard['sales'][$key]['by_currency'][0]['currency']);
            $this->assertSame(100.0, (float) $dashboard['sales'][$key]['by_currency'][0]['revenue']);
        }
        $staff = collect($dashboard['staff'])->firstWhere('id', $seller->id);
        $this->assertSame(count(BuiltInService::ALL), $staff['sales_recorded']);
        $this->assertSame(count(BuiltInService::ALL), $staff['contracts_sold']);
        $this->getJson('/api/educational-reports/employee-performance?employee_id='.$seller->id)->assertOk()
            ->assertJsonPath('data.rows.0.services_sold', count(BuiltInService::ALL))->assertJsonPath('data.rows.0.contracts_sold', count(BuiltInService::ALL));
        $this->getJson('/api/educational-reports/services/export')->assertOk();
    }
}
