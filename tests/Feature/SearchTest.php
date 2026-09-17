<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Customer;
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

    public function test_search_finds_applicants_customers_and_contracts(): void
    {
        $customer = Customer::factory()->create(['name' => 'Zahra Noorzai', 'contact' => '+93700123456', 'email' => 'zahra@example.com']);
        $a = $this->postJson('/api/applicants', ['customer_id' => $customer->id])->assertCreated()->json('data');
        $contract = Contract::factory()->create(['customer_id' => $customer->id, 'contract_number' => 'CTR-99881']);

        $result = $this->getJson('/api/search?q=Noorzai')->assertOk()->json('data');

        $this->assertSame('Noorzai', $result['query']);
        $this->assertNotEmpty($result['groups']['applicants']);
        $this->assertNotEmpty($result['groups']['customers']);
        $this->assertSame($a['id'], $result['groups']['applicants'][0]['id']);
        $this->assertSame('applicant-case', $result['groups']['applicants'][0]['route']['name']);

        // Phone and contract-number lookups reach the same records.
        $this->getJson('/api/search?q=93700123456')->assertOk()->assertJsonFragment(['id' => $a['id']]);
        $phone = $this->getJson('/api/search?q=CTR-99881')->assertOk()->json('data');
        $this->assertSame($contract->id, $phone['groups']['contracts'][0]['id']);

        // A numeric ID lookup finds the case directly.
        $this->getJson("/api/search?q={$a['id']}")->assertOk()->assertJsonFragment(['id' => $a['id']]);
    }

    public function test_search_requires_a_meaningful_query(): void
    {
        $this->getJson('/api/search')->assertUnprocessable();
        $this->getJson('/api/search?q=a')->assertUnprocessable();
    }

    public function test_search_only_returns_groups_the_user_may_open(): void
    {
        Customer::factory()->create(['name' => 'Hidden Customer']);
        Sanctum::actingAs(User::factory()->create(), ['access']);

        $result = $this->getJson('/api/search?q=Hidden')->assertOk()->json('data');

        $this->assertSame([], $result['groups']);
    }
}
