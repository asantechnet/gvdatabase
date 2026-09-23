<?php

namespace Tests\Feature;

use App\Models\ContractType;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContractServiceLinesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('name', 'super_admin')->firstOrFail());
        Sanctum::actingAs($user, ['access']);
    }

    private function customerTemplate(int $typeId, ?int $serviceId, string $name = 'Service agreement'): int
    {
        return $this->postJson('/api/contract-templates', [
            'name' => $name,
            'contract_type_id' => $typeId,
            'service_id' => $serviceId,
            'cover_text' => 'Cover',
            'content' => '<p>Services: {{services.names}} ({{services.count}})</p>',
        ])->assertCreated()->json('data.id');
    }

    public function test_customer_contract_accepts_service_multiselect_with_per_customer_fees(): void
    {
        $type = ContractType::query()->where('slug', 'customer')->firstOrFail();
        $currency = Currency::factory()->create();
        $catalogue = Service::factory()->create(['currency_id' => $currency->id, 'fee' => 500]);
        $extra = Service::factory()->create(['currency_id' => $currency->id, 'fee' => 200]);
        $templateId = $this->customerTemplate($type->id, $catalogue->id);
        $customer = Customer::factory()->create();

        $contract = $this->postJson('/api/contracts', [
            'template_id' => $templateId,
            'customer_id' => $customer->id,
            'status' => 'draft',
            'start_date' => now()->toDateString(),
            'service_ids' => [$catalogue->id, $extra->id],
            'service_lines' => [
                ['service_id' => $catalogue->id, 'fee' => 450, 'currency_id' => $currency->id, 'notes' => 'per-customer discount'],
                ['service_id' => $extra->id],
            ],
        ])->assertCreated()->json('data');

        $this->assertEqualsCanonicalizing([$catalogue->id, $extra->id], $contract['service_ids']);
        $this->assertCount(2, $contract['services']);
        $this->assertDatabaseHas('contract_service', [
            'contract_id' => $contract['id'],
            'service_id' => $catalogue->id,
            'fee' => 450,
        ]);
        $this->assertStringContainsString($catalogue->type, $contract['content']);
    }

    public function test_customer_contract_requires_at_least_one_service(): void
    {
        $type = ContractType::query()->where('slug', 'customer')->firstOrFail();
        $currency = Currency::factory()->create();
        $service = Service::factory()->create(['currency_id' => $currency->id]);
        $templateId = $this->customerTemplate($type->id, $service->id);
        $customer = Customer::factory()->create();

        $this->postJson('/api/contracts', [
            'template_id' => $templateId,
            'customer_id' => $customer->id,
            'status' => 'draft',
            'start_date' => now()->toDateString(),
        ])->assertUnprocessable();
    }

    public function test_inactive_services_are_rejected_in_service_ids(): void
    {
        $type = ContractType::query()->where('slug', 'customer')->firstOrFail();
        $currency = Currency::factory()->create();
        $service = Service::factory()->create(['currency_id' => $currency->id]);
        $templateId = $this->customerTemplate($type->id, $service->id);
        $inactive = Service::factory()->create(['currency_id' => $currency->id, 'status' => 'inactive']);
        $customer = Customer::factory()->create();

        $this->postJson('/api/contracts', [
            'template_id' => $templateId,
            'customer_id' => $customer->id,
            'status' => 'draft',
            'start_date' => now()->toDateString(),
            'service_ids' => [$inactive->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('service_ids.0');
    }

    public function test_contract_templates_can_be_filtered_by_service(): void
    {
        $type = ContractType::query()->where('slug', 'customer')->firstOrFail();
        $otherType = ContractType::query()->where('slug', 'other')->firstOrFail();
        $currency = Currency::factory()->create();
        $service = Service::factory()->create(['currency_id' => $currency->id]);
        $linked = $this->customerTemplate($type->id, $service->id, 'Linked template');
        $generic = $this->customerTemplate($otherType->id, null, 'Generic template');

        $ids = collect($this->getJson("/api/contract-templates?service_ids[]={$service->id}")->assertOk()->json('data'))->pluck('id')->all();
        $this->assertContains($linked, $ids);
        $this->assertContains($generic, $ids);

        $strict = collect($this->getJson("/api/contract-templates?service_ids[]={$service->id}&include_generic=0")->assertOk()->json('data'))->pluck('id')->all();
        $this->assertContains($linked, $strict);
        $this->assertNotContains($generic, $strict);
    }
}
