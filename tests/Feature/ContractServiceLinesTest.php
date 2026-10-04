<?php

namespace Tests\Feature;

use App\Models\ContractType;
use App\Models\Currency;
use App\Models\Applicant;
use App\Models\Package;
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

    private function applicantTemplate(int $typeId, ?int $serviceId, string $name = 'Service agreement'): int
    {
        return $this->postJson('/api/contract-templates', [
            'name' => $name,
            'contract_type_id' => $typeId,
            'service_id' => $serviceId,
            'cover_text' => 'Cover',
            'content' => '<p>Services: {{services.names}} ({{services.count}})</p>',
        ])->assertCreated()->json('data.id');
    }

    public function test_customer_contract_accepts_a_single_service_with_a_per_customer_fee(): void
    {
        $type = ContractType::query()->where('slug', 'customer')->firstOrFail();
        $currency = Currency::factory()->create();
        $catalogue = Service::factory()->create(['currency_id' => $currency->id, 'fee' => 500]);
        $templateId = $this->applicantTemplate($type->id, $catalogue->id);
        $applicant = Applicant::factory()->create();

        $contract = $this->postJson('/api/contracts', [
            'template_id' => $templateId,
            'applicant_id' => $applicant->id,
            'status' => 'draft',
            'start_date' => now()->toDateString(),
            'service_lines' => [
                ['service_id' => $catalogue->id, 'fee' => 450, 'currency_id' => $currency->id, 'notes' => 'per-applicant discount'],
            ],
        ])->assertCreated()->json('data');

        $this->assertSame([$catalogue->id], $contract['service_ids']);
        $this->assertCount(1, $contract['services']);
        $this->assertDatabaseHas('contract_service', [
            'contract_id' => $contract['id'],
            'service_id' => $catalogue->id,
            'fee' => 450,
        ]);
        $this->assertStringContainsString($catalogue->type, $contract['content']);
    }

    public function test_customer_contract_rejects_more_than_one_service(): void
    {
        $type = ContractType::query()->where('slug', 'customer')->firstOrFail();
        $currency = Currency::factory()->create();
        $first = Service::factory()->create(['currency_id' => $currency->id]);
        $second = Service::factory()->create(['currency_id' => $currency->id]);
        $templateId = $this->applicantTemplate($type->id, $first->id);
        $applicant = Applicant::factory()->create();

        // A customer template binds to exactly one catalogue subject, so a
        // contract naming a service outside that binding is rejected. The rule
        // is enforced as a normalized 422 rejection (not a field-level error).
        $this->postJson('/api/contracts', [
            'template_id' => $templateId,
            'applicant_id' => $applicant->id,
            'status' => 'draft',
            'start_date' => now()->toDateString(),
            'service_ids' => [$first->id, $second->id],
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'business.request_rejected');

        $this->postJson('/api/contracts', [
            'template_id' => $templateId,
            'applicant_id' => $applicant->id,
            'status' => 'draft',
            'start_date' => now()->toDateString(),
            'service_lines' => [['service_id' => $first->id], ['service_id' => $second->id]],
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'business.request_rejected');
    }

    public function test_customer_contract_requires_at_least_one_service(): void
    {
        $type = ContractType::query()->where('slug', 'customer')->firstOrFail();
        // A package-bound template carries no service fallback, so the contract
        // must still name at least one service it covers.
        $package = Package::query()->create([
            'name' => 'Service-less bundle',
            'code' => 'PKG-NOSVC',
            'is_active' => true,
        ]);
        $templateId = $this->postJson('/api/contract-templates', [
            'name' => 'Package agreement',
            'contract_type_id' => $type->id,
            'package_id' => $package->id,
            'service_id' => null,
            'cover_text' => 'Cover',
            'content' => '<p>Services: {{services.names}} ({{services.count}})</p>',
        ])->assertCreated()->json('data.id');
        $applicant = Applicant::factory()->create();

        $this->postJson('/api/contracts', [
            'template_id' => $templateId,
            'applicant_id' => $applicant->id,
            'status' => 'draft',
            'start_date' => now()->toDateString(),
        ])->assertUnprocessable();
    }

    public function test_inactive_services_are_rejected_in_service_ids(): void
    {
        $type = ContractType::query()->where('slug', 'customer')->firstOrFail();
        $currency = Currency::factory()->create();
        $service = Service::factory()->create(['currency_id' => $currency->id]);
        $templateId = $this->applicantTemplate($type->id, $service->id);
        $inactive = Service::factory()->create(['currency_id' => $currency->id, 'status' => 'inactive']);
        $applicant = Applicant::factory()->create();

        $this->postJson('/api/contracts', [
            'template_id' => $templateId,
            'applicant_id' => $applicant->id,
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
        $linked = $this->applicantTemplate($type->id, $service->id, 'Linked template');
        $generic = $this->applicantTemplate($otherType->id, null, 'Generic template');

        $ids = collect($this->getJson("/api/contract-templates?service_ids[]={$service->id}")->assertOk()->json('data'))->pluck('id')->all();
        $this->assertContains($linked, $ids);
        $this->assertContains($generic, $ids);

        $strict = collect($this->getJson("/api/contract-templates?service_ids[]={$service->id}&include_generic=0")->assertOk()->json('data'))->pluck('id')->all();
        $this->assertContains($linked, $strict);
        $this->assertNotContains($generic, $strict);
    }
}
