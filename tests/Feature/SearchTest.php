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

class SearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'super_admin']));
        Sanctum::actingAs($user, ['access']);
    }

    public function test_search_finds_applicants_and_contracts(): void
    {
        $a = $this->postJson('/api/applicants', ['name' => 'Zahra Noorzai', 'contact' => '+93700123456', 'email' => 'zahra@example.com'])->assertCreated()->json('data');
        $contract = Contract::factory()->create([
            'applicant_id' => $a['id'],
            'contract_number' => 'CTR-99881',
            'service' => 'scholarship',
            'contract_type_id' => ContractType::where('slug', 'customer')->firstOrFail()->id,
        ]);

        $result = $this->getJson('/api/search?q=Noorzai')->assertOk()->json('data');

        $this->assertSame('Noorzai', $result['query']);
        $this->assertNotEmpty($result['groups']['applicants']);
        $this->assertSame($a['id'], $result['groups']['applicants'][0]['id']);
        $this->assertSame('applicant-case', $result['groups']['applicants'][0]['route']['name']);

        // Phone and contract-number lookups reach the same records.
        $this->getJson('/api/search?q=93700123456')->assertOk()->assertJsonFragment(['id' => $a['id']]);
        $phone = $this->getJson('/api/search?q=CTR-99881')->assertOk()->json('data');
        $this->assertSame($contract->id, $phone['groups']['contracts'][0]['id']);
        $this->assertSame('scholarship-contracts', $phone['groups']['contracts'][0]['route']['name']);

        // A numeric ID lookup finds the case directly.
        $this->getJson("/api/search?q={$a['id']}")->assertOk()->assertJsonFragment(['id' => $a['id']]);
    }

    public function test_search_requires_a_meaningful_query(): void
    {
        $this->getJson('/api/search')->assertUnprocessable();
        $this->getJson('/api/search?q=a')->assertUnprocessable();
    }

    public function test_search_routes_built_in_services_without_exposing_retired_catalogue_contracts(): void
    {
        $type = ContractType::where('slug', 'customer')->firstOrFail();
        $expected = [];
        foreach (['scholarship', 'test_offer', 'academy'] as $service) {
            $contract = Contract::factory()->create([
                'contract_type_id' => $type->id, 'service' => $service,
                'contract_number' => 'SEARCH-'.strtoupper($service),
            ]);
            $expected[$contract->id] = str_replace('_', '-', $service).'-contracts';
        }
        Contract::factory()->create([
            'contract_type_id' => $type->id, 'service' => null,
            'contract_number' => 'SEARCH-RETIRED-CATALOGUE',
        ]);

        $rows = $this->getJson('/api/search?q=SEARCH')->assertOk()->json('data.groups.contracts');
        $this->assertCount(3, $rows);
        foreach ($rows as $row) {
            $this->assertSame($expected[$row['id']], $row['route']['name']);
            $this->assertSame($row['id'], $row['route']['query']['contractId']);
        }
    }

    public function test_search_only_returns_groups_the_user_may_open(): void
    {
        Applicant::factory()->create(['name' => 'Hidden Applicant']);
        Sanctum::actingAs(User::factory()->create(), ['access']);

        $result = $this->getJson('/api/search?q=Hidden')->assertOk()->json('data');

        $this->assertSame([], $result['groups']);
    }
}
