<?php

namespace Tests\Feature;

use App\Models\ContractTemplate;
use App\Models\ContractType;
use App\Models\Permission;
use App\Models\Role;
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
        ])->assertUnprocessable()->assertJsonValidationErrors('service');
    }

    public function test_template_viewers_cannot_access_the_removed_package_catalogue(): void
    {
        $viewer = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'template_viewer']);
        $role->permissions()->sync(Permission::query()->where('name', 'view_contract_templates')->pluck('id'));
        $viewer->roles()->attach($role);

        Sanctum::actingAs($viewer, ['access']);

        $this->getJson('/api/packages')->assertNotFound();

        $this->getJson('/api/contract-templates')->assertOk();
    }

    public function test_removed_catalogue_bindings_are_rejected_without_changing_the_template(): void
    {
        $type = ContractType::where('slug', 'customer')->firstOrFail();
        $template = ContractTemplate::create(['name' => 'Academy terms', 'contract_type_id' => $type->id, 'service' => 'academy', 'content' => '', 'cover_text' => '']);
        foreach (['service_id' => 1, 'package_id' => 1, 'service_ids' => [1], 'service_scopes' => [['service_id' => 1]]] as $field => $value) {
            $this->putJson('/api/contract-templates/'.$template->id, [$field => $value])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertSame('academy', $template->fresh()->service);
    }
}
