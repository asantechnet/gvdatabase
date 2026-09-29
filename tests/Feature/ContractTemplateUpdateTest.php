<?php

namespace Tests\Feature;

use App\Models\ContractTemplate;
use App\Models\ContractType;
use App\Models\Applicant;
use App\Models\Currency;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContractTemplateUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->firstOrCreate(['name' => 'super_admin']));
        Sanctum::actingAs($user, ['access']);
    }

    public function test_partial_update_uses_individual_validation_rules(): void
    {
        $type = ContractType::query()->where('slug', 'customer')->firstOrFail();
        $template = ContractTemplate::query()->create([
            'name' => 'Applicant Agreement',
            'contract_type_id' => $type->id,
            'cover_text' => 'Cover',
            'content' => '<p>Original terms</p>',
        ]);

        $this->putJson("/api/contract-templates/{$template->id}", [
            'name' => 'Updated Applicant Agreement',
            'content' => '<p>Updated terms</p>',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Updated Applicant Agreement')
            ->assertJsonPath('data.content', '<p>Updated terms</p>');
    }

    public function test_customer_template_requires_a_service(): void
    {
        $type = ContractType::query()->where('slug', 'customer')->firstOrFail();

        $this->postJson('/api/contract-templates', [
            'name' => 'Applicant Agreement',
            'contract_type_id' => $type->id,
            'cover_text' => 'Cover',
            'content' => '<p>Terms</p>',
        ])->assertUnprocessable()->assertJsonValidationErrors('service_id');
    }

    public function test_customer_template_accepts_a_package_instead_of_a_service(): void
    {
        $type = ContractType::query()->where('slug', 'customer')->firstOrFail();
        $currency = Currency::factory()->create();
        $service = Service::factory()->create(['currency_id' => $currency->id]);
        $package = $this->postJson('/api/packages', [
            'name' => 'Template Test Bundle',
            'service_ids' => [$service->id],
            'currency_id' => $currency->id,
            'fee' => 600,
        ])->assertCreated()->json('data');

        // A template can be for a package: no service scope is required and
        // the service binding stays empty.
        $templateId = $this->postJson('/api/contract-templates', [
            'name' => 'Package Agreement',
            'contract_type_id' => $type->id,
            'package_id' => $package['id'],
            'service_id' => null,
            'cover_text' => 'Cover',
            'content' => '<p>Terms</p>',
        ])->assertCreated()
            ->assertJsonPath('data.package_id', $package['id'])
            ->assertJsonPath('data.service_id', null)
            ->json('data.id');

        $this->assertDatabaseHas('contract_templates', ['id' => $templateId, 'package_id' => $package['id'], 'service_id' => null]);
        $this->assertDatabaseCount('contract_template_services', 0);

        // A template can never bind both subjects at once.
        $this->postJson('/api/contract-templates', [
            'name' => 'Confused Agreement',
            'contract_type_id' => $type->id,
            'package_id' => $package['id'],
            'service_id' => $service->id,
            'service_scopes' => [['service_id' => $service->id]],
            'cover_text' => 'Cover',
            'content' => '<p>Terms</p>',
        ])->assertUnprocessable()->assertJsonValidationErrors('service_id');

        // Switching the template from package to service clears the package.
        $this->putJson("/api/contract-templates/{$templateId}", [
            'service_id' => $service->id,
            'service_scopes' => [['service_id' => $service->id]],
            'package_id' => null,
        ])->assertOk()
            ->assertJsonPath('data.package_id', null)
            ->assertJsonPath('data.service_id', $service->id);

        $this->assertDatabaseHas('contract_templates', ['id' => $templateId, 'package_id' => null, 'service_id' => $service->id]);
        $this->assertDatabaseHas('contract_template_services', ['contract_template_id' => $templateId, 'service_id' => $service->id]);

        // Switching the template from service back to package clears the service.
        $this->putJson("/api/contract-templates/{$templateId}", [
            'package_id' => $package['id'],
            'service_id' => null,
        ])->assertOk()
            ->assertJsonPath('data.package_id', $package['id'])
            ->assertJsonPath('data.service_id', null);

        $this->assertDatabaseHas('contract_templates', ['id' => $templateId, 'package_id' => $package['id'], 'service_id' => null]);
        $this->assertDatabaseCount('contract_template_services', 0);
    }
}
