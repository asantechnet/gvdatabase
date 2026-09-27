<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractType;
use App\Models\Currency;
use App\Models\Applicant;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ServiceContractNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_contracts_can_be_filtered_by_service_or_missing_service(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::query()->where('name', 'super_admin')->firstOrFail());
        Sanctum::actingAs($admin, ['access']);
        $currency = Currency::factory()->create();
        $service = Service::factory()->create(['currency_id' => $currency->id]);
        $customerTypeId = ContractType::query()->where('slug', 'customer')->value('id');
        $applicant = Applicant::factory()->create();

        $matching = Contract::factory()->create(['contract_type_id' => $customerTypeId, 'applicant_id' => $applicant->id, 'counterparty' => $applicant->name]);
        $matching->contractServices()->create(['service_id' => $service->id]);
        $unassigned = Contract::factory()->create(['contract_type_id' => $customerTypeId, 'applicant_id' => $applicant->id, 'counterparty' => $applicant->name]);

        $this->getJson("/api/contracts?audience=customers&service_ids[]={$service->id}")
            ->assertOk()->assertJsonPath('data.0.id', $matching->id);
        $this->getJson("/api/contracts?audience=customers&service_id={$service->id}")
            ->assertOk()->assertJsonPath('data.0.id', $matching->id);
        $this->getJson('/api/contracts?audience=customers&without_service=1')
            ->assertOk()->assertJsonPath('data.0.id', $unassigned->id);
    }
}
