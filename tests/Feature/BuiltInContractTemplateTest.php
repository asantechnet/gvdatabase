<?php

namespace Tests\Feature;

use App\Models\ContractTemplate;
use App\Models\ContractType;
use App\Models\Country;
use App\Models\Language;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Contracts\BuiltInContractTemplateService;
use App\Support\BuiltInService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BuiltInContractTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
    }

    public function test_first_use_creates_one_unconfigured_editable_template_per_service(): void
    {
        foreach (BuiltInService::ALL as $service) {
            $id = $this->postJson("/api/contract-templates/built-in/{$service}")
                ->assertCreated()->assertJsonPath('data.service', $service)
                ->assertJsonPath('data.type_slug', 'customer')->assertJsonPath('data.versions', [])
                ->json('data.id');
            $this->postJson("/api/contract-templates/built-in/{$service}")
                ->assertOk()->assertJsonPath('data.id', $id);
            $this->assertDatabaseHas('contract_templates', ['id' => $id, 'is_active' => false]);
            $this->getJson("/api/contract-templates?service={$service}")
                ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
        }
        $this->assertSame(count(BuiltInService::ALL), ContractTemplate::whereNotNull('service')->count());
    }

    public function test_unknown_services_are_rejected_without_creating_a_template(): void
    {
        $this->postJson('/api/contract-templates/built-in/unknown')->assertNotFound();
        $this->getJson('/api/contract-templates?service=unknown')->assertUnprocessable();
        $this->assertSame(0, ContractTemplate::whereNotNull('service')->count());
    }

    public function test_provisioning_requires_template_management_permission(): void
    {
        $role = Role::firstOrCreate(['name' => 'template_viewer']);
        $role->permissions()->sync(Permission::where('name', 'view_contract_templates')->pluck('id'));
        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);
        Sanctum::actingAs($user, ['access']);
        $this->postJson('/api/contract-templates/built-in/academy')->assertForbidden();
        $this->assertSame(0, ContractTemplate::whereNotNull('service')->count());
    }

    public function test_editing_and_version_activation_keep_the_same_service_template(): void
    {
        $id = $this->postJson('/api/contract-templates/built-in/scholarship')->assertCreated()->json('data.id');
        $this->putJson("/api/contract-templates/{$id}", ['name' => 'Scholarship agreement', 'cover_text' => 'Edited cover'])
            ->assertOk()->assertJsonPath('data.service', 'scholarship');
        $country = Country::create(['name' => 'Destination', 'code' => 'DS']);
        $language = Language::create(['name' => 'English', 'code' => 'en']);
        $version = $this->postJson("/api/contract-templates/{$id}/versions", [
            'country_id' => $country->id, 'language_id' => $language->id,
            'name' => 'English agreement', 'content' => '<p>{{customer.name}}</p>',
        ])->assertCreated()->assertJsonPath('data.status', 'draft')->json('data.id');
        $this->postJson("/api/contract-templates/{$id}/versions/{$version}/activate")
            ->assertOk()->assertJsonPath('data.status', 'active');
        $this->postJson('/api/contract-templates/built-in/scholarship')->assertOk()
            ->assertJsonPath('data.id', $id)->assertJsonPath('data.name', 'Scholarship agreement')
            ->assertJsonPath('data.versions.0.id', $version);
        $this->putJson("/api/contract-templates/{$id}", ['content' => '<p>Cannot replace issued versions</p>'])->assertOk();
        $this->assertDatabaseHas('contract_template_versions', ['id' => $version, 'content' => '<p>{{customer.name}}</p>']);
    }

    public function test_builtin_binding_is_unique_immutable_and_protected_from_deletion(): void
    {
        $id = $this->postJson('/api/contract-templates/built-in/academy')->assertCreated()->json('data.id');
        $type = ContractType::where('slug', 'customer')->firstOrFail();
        $this->postJson('/api/contract-templates', [
            'name' => 'Duplicate academy', 'contract_type_id' => $type->id, 'service' => 'academy',
        ])->assertUnprocessable()->assertJsonValidationErrors('service');
        foreach ([null, 'test_offer'] as $service) {
            $this->putJson("/api/contract-templates/{$id}", ['service' => $service])
                ->assertUnprocessable()->assertJsonValidationErrors('service');
        }
        $other = ContractType::where('slug', 'employment')->firstOrFail();
        $this->putJson("/api/contract-templates/{$id}", ['contract_type_id' => $other->id])
            ->assertUnprocessable()->assertJsonValidationErrors('contract_type_id');
        $this->deleteJson("/api/contract-templates/{$id}")->assertConflict();
        $this->assertDatabaseHas('contract_templates', ['id' => $id, 'service' => 'academy', 'deleted_at' => null]);
    }

    public function test_archived_builtin_template_is_restored_without_losing_its_content(): void
    {
        $templates = app(BuiltInContractTemplateService::class);
        $template = $templates->ensure('test_offer');
        $template->update(['name' => 'Edited test agreement', 'content' => '<p>Edited terms</p>']);
        $template->delete();
        $restored = $templates->ensure('test_offer');
        $this->assertSame($template->id, $restored->id);
        $this->assertSame('<p>Edited terms</p>', $restored->content);
        $this->assertFalse($restored->trashed());
        $this->assertSame(1, ContractTemplate::withTrashed()->where('service', 'test_offer')->count());
    }

    public function test_catalogue_binding_endpoints_cannot_repurpose_a_builtin_template(): void
    {
        $id = $this->postJson('/api/contract-templates/built-in/academy')->assertCreated()->json('data.id');
        foreach (['services/1', 'packages/1'] as $path) {
            $this->postJson("/api/{$path}/contract-template", ['contract_template_id' => $id])->assertNotFound();
        }
        $this->putJson("/api/contract-templates/{$id}", ['service_id' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('service_id');
        $this->assertDatabaseHas('contract_templates', ['id' => $id]);
    }

    public function test_direct_creation_accepts_a_builtin_without_a_catalogue_binding(): void
    {
        $type = ContractType::where('slug', 'customer')->firstOrFail();
        $this->postJson('/api/contract-templates', [
            'name' => 'Scholarship terms', 'contract_type_id' => $type->id,
            'service' => 'scholarship', 'content' => '<p>{{customer.name}}</p>',
        ])->assertCreated()->assertJsonPath('data.service', 'scholarship')
            ->assertJsonMissingPath('data.service_id')->assertJsonMissingPath('data.package_id');
    }

    public function test_the_database_prevents_duplicate_builtin_templates(): void
    {
        $template = app(BuiltInContractTemplateService::class)->ensure('academy');
        $this->expectException(QueryException::class);
        ContractTemplate::create($template->only(['name', 'service', 'contract_type_id', 'content', 'cover_text']));
    }

    public function test_unbound_templates_can_select_each_builtin_service_when_changed_to_customer_type(): void
    {
        $customerType = ContractType::where('slug', 'customer')->firstOrFail();
        $otherType = ContractType::where('slug', 'other')->firstOrFail();
        foreach (BuiltInService::ALL as $service) {
            $template = ContractTemplate::create([
                'name' => ucfirst($service).' terms', 'contract_type_id' => $otherType->id,
                'content' => '<p>Approved terms</p>', 'cover_text' => '',
            ]);
            $this->putJson("/api/contract-templates/{$template->id}", [
                'contract_type_id' => $customerType->id, 'service' => $service,
            ])->assertOk()->assertJsonPath('data.service', $service)
                ->assertJsonPath('data.type_slug', 'customer');
            $this->assertSame($template->id, app(BuiltInContractTemplateService::class)->ensure($service)->id);
            $this->assertSame('<p>Approved terms</p>', $template->fresh()->content);
        }
    }

    public function test_catalogue_template_filters_are_rejected(): void
    {
        app(BuiltInContractTemplateService::class)->ensure('academy');
        $this->getJson('/api/contract-templates?service_ids[]=1')->assertUnprocessable()->assertJsonValidationErrors('service_ids');
    }
}
