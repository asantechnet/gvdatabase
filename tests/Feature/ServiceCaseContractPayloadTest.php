<?php

namespace Tests\Feature;

use App\Models\ApplicantContract;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesBuiltInContracts;
use Tests\TestCase;

class ServiceCaseContractPayloadTest extends TestCase
{
    use CreatesBuiltInContracts, RefreshDatabase;

    public function test_service_case_includes_the_parent_contract_without_sibling_lines(): void
    {
        $user = User::factory()->create(['acc_status' => 'active']);
        $user->roles()->attach(Role::query()->where('name', 'super_admin')->firstOrFail());
        Sanctum::actingAs($user, ['access']);

        $created = $this->createBuiltInContract('scholarship');
        $line = ApplicantContract::query()->where('contract_id', $created['id'])->sole();

        $payload = $this->getJson('/api/applicant-contracts/'.$line->id)
            ->assertOk()
            ->assertJsonPath('data.contract.id', $created['id'])
            ->assertJsonPath('data.contract.contract_number', $created['contract_number'])
            ->assertJsonMissingPath('data.contract.services')
            ->json('data');

        $this->assertSame($line->id, $payload['id']);
        $this->assertNotEmpty($payload['contract']['contract_template_version'] ?? null);
    }
}
