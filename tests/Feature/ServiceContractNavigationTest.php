<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Contract;
use App\Models\ContractType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ServiceContractNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_builtin_workspaces_filter_by_service_and_applicant_without_catalogue_fallbacks(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'super_admin')->firstOrFail());
        Sanctum::actingAs($admin, ['access']);
        $typeId = ContractType::where('slug', 'customer')->value('id');
        $applicant = Applicant::factory()->create();
        $matching = Contract::factory()->create(['contract_type_id' => $typeId, 'service' => 'academy', 'applicant_id' => $applicant->id]);
        Contract::factory()->create(['contract_type_id' => $typeId, 'service' => 'academy', 'applicant_id' => Applicant::factory()->create()->id]);
        Contract::factory()->create(['contract_type_id' => $typeId, 'service' => 'test_offer', 'applicant_id' => $applicant->id]);
        $legacy = Contract::factory()->create(['contract_type_id' => $typeId, 'service' => null, 'applicant_id' => $applicant->id]);
        $this->getJson("/api/contracts?service=academy&applicant_id={$applicant->id}")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $matching->id);
        $ids = collect($this->getJson('/api/contracts?audience=customers')->assertOk()->json('data'))->pluck('id');
        $this->assertCount(3, $ids);
        $this->assertNotContains($legacy->id, $ids);
        foreach (['service_id=1', 'service_ids[]=1', 'without_service=1', 'service=unknown'] as $query) {
            $this->getJson('/api/contracts?'.$query)->assertUnprocessable();
        }
    }

    public function test_search_results_link_to_the_actual_workspace_and_contract(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'super_admin')->firstOrFail());
        Sanctum::actingAs($admin, ['access']);
        foreach (['scholarship', 'test_offer', 'academy', 'employment', 'other'] as $scope) {
            $type = in_array($scope, ['employment', 'other']) ? $scope : 'customer';
            $contract = Contract::factory()->create([
                'contract_type_id' => ContractType::where('slug', $type)->value('id'),
                'service' => $type === 'customer' ? $scope : null,
                'name' => 'Workspace '.$scope,
            ]);
            $route = $this->getJson('/api/search?q=Workspace%20'.$scope)->assertOk()->json('data.groups.contracts.0.route');
            $expected = $scope === 'employment' ? 'employee-contracts' : str_replace('_', '-', $scope).'-contracts';
            $this->assertSame($expected, $route['name']);
            $this->assertSame($contract->id, $route['query']['contractId']);
        }
    }
}
