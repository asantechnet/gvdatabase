<?php

namespace Tests\Feature;

use App\Models\ContractTemplate;
use App\Models\ContractType;
use App\Models\Customer;
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

    public function test_contract_creation_accepts_localized_whole_month_duration(): void
    {
        $type = ContractType::query()->where('slug', 'customer')->firstOrFail();
        $template = ContractTemplate::query()->create([
            'name' => 'Customer Agreement',
            'contract_type_id' => $type->id,
            'cover_text' => 'Cover',
            'content' => '<p>Terms</p>',
        ]);
        $customer = Customer::factory()->create();

        $this->postJson('/api/contracts', [
            'template_id' => $template->id,
            'customer_id' => $customer->id,
            'status' => 'active',
            'start_date' => '2026-01-15',
            'duration_months' => '۱۲',
        ])->assertCreated()
            ->assertJsonPath('data.customer_id', $customer->id)
            ->assertJsonPath('data.duration_months', 12)
            ->assertJsonPath('data.end_date', '2027-01-15');
    }
}
