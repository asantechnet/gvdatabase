<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\ContractType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContractEditWindowTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_edits_remain_exempt_from_the_employee_edit_window(): void
    {
        $manager = User::factory()->create(['roles' => ['manager']]);
        $type = ContractType::query()->where('slug', 'other')->firstOrFail();
        $template = ContractTemplate::factory()->create([
            'contract_type_id' => $type->id,
            'type' => 'Other',
        ]);
        $contract = Contract::factory()->create([
            'template_id' => $template->id,
            'status' => 'draft',
            'created_at' => now('UTC')->subHours(2),
        ]);

        Sanctum::actingAs($manager, ['access']);

        $this->putJson("/api/contracts/{$contract->id}", ['name' => 'Late contract edit'])
            ->assertOk()
            ->assertJsonPath('data.contract', 'Late contract edit');

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'name' => 'Late contract edit',
        ]);

        // Employee-only policy: no contract write may create an employee
        // override audit, regardless of record age.
        $this->assertDatabaseMissing('audit_logs', [
            'event' => 'edit_window_overridden',
            'auditable_type' => Contract::class,
            'auditable_id' => $contract->id,
        ]);
    }
}
