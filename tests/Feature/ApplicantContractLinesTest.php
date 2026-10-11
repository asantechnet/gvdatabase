<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApplicantContractLinesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'super_admin')->firstOrFail());
        Sanctum::actingAs($user, ['access']);
    }

    private function applicantTemplate(int $typeId, ?string $service, string $name = 'Service agreement'): int
    {
        return $this->postJson('/api/contract-templates', [
            'name' => $name, 'contract_type_id' => $typeId, 'service' => $service,
            'content' => '<p>Agreement</p>',
        ])->assertCreated()->json('data.id');
    }

    public function test_catalogue_lines_cannot_be_created_through_the_retired_generic_command(): void
    {
        $type = ContractType::where('slug', 'customer')->firstOrFail();
        $template = $this->applicantTemplate($type->id, 'scholarship');
        foreach (['service_id' => 1, 'service_ids' => [1], 'service_lines' => [['service_id' => 1]]] as $field => $value) {
            $this->postJson('/api/contracts', ['template_id' => $template, $field => $value])
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertDatabaseCount('contracts', 0);
        $this->assertDatabaseCount('applicant_contract', 0);
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_customer_drafts_cannot_be_repriced_reassigned_or_restored_by_generic_updates(): void
    {
        $contract = Contract::factory()->create([
            'contract_type_id' => ContractType::where('slug', 'customer')->value('id'),
            'service' => 'academy', 'status' => 'draft',
        ]);
        foreach (['service_ids' => [1], 'service_lines' => [['service_id' => 1]]] as $field => $value) {
            $this->putJson('/api/contracts/'.$contract->id, [$field => $value])
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->putJson('/api/contracts/'.$contract->id, ['name' => 'Must not change'])->assertUnprocessable();
        $this->assertNotSame('Must not change', $contract->fresh()->name);
    }

    public function test_contract_templates_can_be_filtered_by_service(): void
    {
        $type = ContractType::query()->where('slug', 'customer')->firstOrFail();
        $otherType = ContractType::query()->where('slug', 'other')->firstOrFail();
        $linked = $this->applicantTemplate($type->id, 'scholarship', 'Linked template');
        $generic = $this->applicantTemplate($otherType->id, null, 'Generic template');

        $ids = collect($this->getJson('/api/contract-templates?service=scholarship')->assertOk()->json('data'))->pluck('id')->all();
        $this->assertContains($linked, $ids);
        $this->assertNotContains($generic, $ids);

        $this->getJson('/api/contract-templates?service_ids[]=1')->assertUnprocessable();
    }
}
