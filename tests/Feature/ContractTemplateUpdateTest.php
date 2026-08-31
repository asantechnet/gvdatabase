<?php

namespace Tests\Feature;

use App\Models\ContractTemplate;
use App\Models\ContractType;
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
            'name' => 'Customer Agreement',
            'contract_type_id' => $type->id,
            'cover_text' => 'Cover',
            'content' => '<p>Original terms</p>',
        ]);

        $this->putJson("/api/contract-templates/{$template->id}", [
            'name' => 'Updated Customer Agreement',
            'content' => '<p>Updated terms</p>',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Updated Customer Agreement')
            ->assertJsonPath('data.content', '<p>Updated terms</p>');
    }
}
