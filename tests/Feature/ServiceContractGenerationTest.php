<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\ContractType;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Assigning a service to a customer creates that service's contract from its
 * own template, and repeat sales become extension contracts (see
 * service-contract-linkage-plan.md).
 */
class ServiceContractGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'super_admin']));
        Sanctum::actingAs($user, ['access']);
    }

    public function test_assigning_a_service_creates_its_contract_from_the_service_template(): void
    {
        $service = $this->service();
        $template = $this->templateFor($service, '<p>{{customer.name}} bought {{service.name}}.</p>');
        $applicant = Applicant::factory()->create(['name' => 'Ali Rahimi']);
        $this->seedApplicantDocuments($applicant);
        $employee = Employee::factory()->create();
        $currency = $service->currency;

        $sale = $this->postJson('/api/educational-services/scholarships', [
            'applicant_id' => $applicant->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'currency_id' => $currency->id,
            'date' => '2026-09-27',
            'fee' => 700,
        ])->assertCreated()->json('data');

        $this->assertNotNull($sale['contract_id']);
        $this->assertSame('primary', $sale['contract_role']);

        $contract = Contract::findOrFail($sale['contract_id']);
        $this->assertSame($service->id, $contract->source_service_id);
        $this->assertSame($template->id, $contract->template_id);
        $this->assertTrue($contract->contractServices()->where('service_id', $service->id)->exists());
        $this->assertStringContainsString('Ali Rahimi', $contract->content ?? '');
        $this->assertStringContainsString($service->type, $contract->content ?? '');
    }

    public function test_a_service_without_a_template_gets_one_automatically(): void
    {
        $service = $this->service();
        $applicant = Applicant::factory()->create();
        $this->seedApplicantDocuments($applicant);
        $employee = Employee::factory()->create();

        $this->postJson('/api/educational-services/scholarships', [
            'applicant_id' => $applicant->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'currency_id' => $service->currency_id,
            'date' => '2026-09-27',
            'fee' => 300,
        ])->assertCreated();

        $template = ContractTemplate::query()->where('service_id', $service->id)->first();
        $this->assertNotNull($template);
        $this->assertTrue((bool) $template->is_active);
        $this->assertNotSame('', (string) $template->content);
    }

    public function test_a_repeat_sale_becomes_an_extension_linked_to_the_primary_contract(): void
    {
        $service = $this->service();
        $this->templateFor($service, '<p>{{customer.name}} — {{service.name}}</p>');
        $applicant = Applicant::factory()->create();
        $this->seedApplicantDocuments($applicant);
        $employee = Employee::factory()->create();

        $first = $this->postJson('/api/educational-services/scholarships', $this->payload($service, $applicant, $employee))
            ->assertCreated()->json('data.contract_id');
        $second = $this->postJson('/api/educational-services/scholarships', $this->payload($service, $applicant, $employee))
            ->assertCreated()->json('data');

        $extension = Contract::findOrFail($second['contract_id']);
        $this->assertSame('extension', $extension->contract_role);
        $this->assertSame($first, $extension->parent_contract_id);
        $this->assertSame($second['contract_id'], Contract::findOrFail($first)->extensions()->first()->id);
    }

    public function test_a_repeat_sale_can_be_refused_instead_of_extended(): void
    {
        $service = $this->service();
        $this->templateFor($service, '<p>{{customer.name}}</p>');
        $applicant = Applicant::factory()->create();
        $this->seedApplicantDocuments($applicant);
        $employee = Employee::factory()->create();

        $this->postJson('/api/educational-services/scholarships', $this->payload($service, $applicant, $employee))
            ->assertCreated();

        $this->postJson('/api/educational-services/scholarships', [
            ...$this->payload($service, $applicant, $employee),
            'extend' => false,
        ])->assertStatus(422)->assertJsonValidationErrors('service_id');
    }

    public function test_service_catalogue_exposes_and_binds_its_contract_template(): void
    {
        $service = $this->service();

        $this->getJson("/api/services/{$service->id}")
            ->assertOk()
            ->assertJsonPath('has_contract_template', false);

        $this->postJson("/api/services/{$service->id}/contract-template", ['name' => 'Custom service agreement'])
            ->assertOk()
            ->assertJsonPath('has_contract_template', true)
            ->assertJsonPath('contract_template.name', 'Custom service agreement');
    }

    /** @return array<string, mixed> */
    private function payload(Service $service, Applicant $applicant, Employee $employee): array
    {
        return [
            'applicant_id' => $applicant->id,
            'service_id' => $service->id,
            'employee_id' => $employee->id,
            'currency_id' => $service->currency_id,
            'date' => '2026-09-27',
            'fee' => 700,
        ];
    }

    private function service(): Service
    {
        $currency = Currency::factory()->create();

        return Service::factory()->create([
            'currency_id' => $currency->id,
            'category' => 'scholarship',
            'status' => 'active',
            'fee' => 800,
        ]);
    }

    private function templateFor(Service $service, string $content): ContractTemplate
    {
        $type = ContractType::query()->where('slug', 'customer')->firstOrFail();

        return ContractTemplate::create([
            'name' => "{$service->type} Agreement",
            'contract_type_id' => $type->id,
            'service_id' => $service->id,
            'cover_text' => $service->type,
            'content' => $content,
            'is_active' => true,
        ]);
    }
}
