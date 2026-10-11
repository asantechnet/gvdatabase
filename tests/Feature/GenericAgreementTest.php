<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\ContractType;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GenericAgreementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create(['name' => 'Agreement signatory']);
        $user->roles()->attach(Role::where('name', 'super_admin')->firstOrFail());
        Sanctum::actingAs($user, ['access']);
    }

    private function template(string $slug = 'other', string $content = '<p>Agreement</p>'): ContractTemplate
    {
        return ContractTemplate::factory()->create([
            'contract_type_id' => ContractType::where('slug', $slug)->value('id'),
            'content' => $content,
        ]);
    }

    public function test_preview_resolves_the_selected_applicant_and_legacy_editor_marks_without_issuing_a_contract(): void
    {
        $template = $this->template('other', '<p><span data-placeholder-key="customer.name">Customer name</span> {{customer.email}} {{contract.number}}</p><table><tr><td>{{customer.phone}}</td></tr></table>');
        $first = Applicant::factory()->create(['name' => 'First & Applicant', 'email' => 'first@example.com', 'contact' => '123']);
        $second = Applicant::factory()->create(['name' => 'Second Applicant', 'email' => 'second@example.com', 'contact' => '456']);
        foreach ([$first, $second] as $applicant) {
            $content = $this->postJson('/api/contracts/preview', [
                'template_id' => $template->id, 'applicant_id' => $applicant->id, 'start_date' => '2026-10-08',
            ])->assertOk()->json('data.content');
            $this->assertStringContainsString(e($applicant->name), $content);
            $this->assertStringContainsString($applicant->email, $content);
            $this->assertStringContainsString('<td>'.$applicant->contact.'</td>', $content);
            $this->assertStringContainsString('{{contract.number}}', $content);
            $this->assertStringNotContainsString('data-placeholder-key', $content);
        }
        $this->assertDatabaseCount('contracts', 0);
        $issued = $this->postJson('/api/contracts', [
            'template_id' => $template->id, 'applicant_id' => $first->id,
        ])->assertCreated()->json('data');
        $this->assertStringContainsString(e($first->name), $issued['content']);
        $this->assertStringContainsString($issued['contract_number'], $issued['content']);
    }

    public function test_preview_rejects_an_applicant_outside_the_employees_scope(): void
    {
        $template = $this->template();
        $owner = Employee::factory()->create();
        $applicant = Applicant::factory()->create(['responsible_employee_id' => $owner->id]);
        Sanctum::actingAs(User::factory()->create(['roles' => ['staff']]), ['access']);
        $this->postJson('/api/contracts/preview', [
            'template_id' => $template->id, 'applicant_id' => $applicant->id, 'start_date' => '2026-10-08',
        ])->assertForbidden();
    }

    public function test_employment_creation_renders_the_assigned_number_and_freezes_the_employee(): void
    {
        $employee = Employee::factory()->create(['name' => 'Contract Employee']);
        $template = $this->template('employment', '<p>{{contract.number}} {{employee.name}}</p>');
        $response = $this->postJson('/api/contracts', ['template_id' => $template->id, 'employee_id' => $employee->id])->assertCreated();
        $contract = Contract::findOrFail($response->json('data.id'));
        $this->assertStringContainsString($contract->contract_number, $contract->content);
        $this->assertStringContainsString('Contract Employee', $contract->content);
        $this->assertSame('Agreement signatory', $contract->company_signatory_name);
        $this->assertSame('Contract Employee', $contract->snapshot_data['entity']['employee']['name']);
        $employee->update(['name' => 'Changed Employee']);
        $this->getJson('/api/contracts/'.$contract->id)->assertOk()->assertJsonPath('data.counterparty', 'Contract Employee');
        $this->putJson('/api/contracts/'.$contract->id, ['name' => 'Changed'])->assertUnprocessable();
        $this->deleteJson('/api/contracts/'.$contract->id)->assertUnprocessable();
    }

    public function test_other_drafts_allow_metadata_edits_and_party_changes_without_retaining_old_contact_details(): void
    {
        $template = $this->template();
        $first = Applicant::factory()->create(['email' => 'first@example.com']);
        $second = Applicant::factory()->create(['email' => null, 'contact' => null, 'address' => null]);
        $response = $this->postJson('/api/contracts', ['template_id' => $template->id, 'applicant_id' => $first->id, 'counterparty' => '', 'status' => 'draft'])->assertCreated();
        $id = $response->json('data.id');
        $template->update(['content' => '<p>New terms</p>']);
        $this->putJson('/api/contracts/'.$id, ['name' => 'Metadata only'])->assertOk();
        $this->assertSame('<p>Agreement</p>', Contract::findOrFail($id)->content);
        $this->putJson('/api/contracts/'.$id, ['applicant_id' => $second->id])->assertOk()->assertJsonPath('data.counterparty', $second->name);
        $contract = Contract::findOrFail($id);
        $this->assertNull($contract->counterparty_email);
        $this->assertNull($contract->counterparty_contact);
        $this->assertNull($contract->counterparty_address);
        $this->assertSame('<p>New terms</p>', $contract->content);
        $this->deleteJson('/api/contracts/'.$id)->assertOk();
    }

    public function test_invalid_dates_and_render_failures_roll_back_creation_and_updates(): void
    {
        $template = $this->template();
        $payload = ['template_id' => $template->id, 'counterparty' => 'External party', 'status' => 'draft', 'start_date' => '2026-10-08'];
        $this->postJson('/api/contracts', [...$payload, 'end_date' => '2026-10-07'])->assertUnprocessable();
        $this->assertDatabaseCount('contracts', 0);
        $id = $this->postJson('/api/contracts', $payload)->assertCreated()->json('data.id');
        $template->update(['content' => '<p>{{unsupported.value}}</p>']);
        $this->putJson('/api/contracts/'.$id, ['status' => 'active', 'name' => 'Must roll back'])->assertUnprocessable();
        $this->assertSame('draft', Contract::findOrFail($id)->status);
        $this->assertDatabaseMissing('contracts', ['name' => 'Must roll back']);
        $this->postJson('/api/contracts', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('contracts', 1);
    }

    public function test_generic_creation_rejects_catalogue_fields_and_customer_or_company_templates(): void
    {
        $template = $this->template();
        foreach (['service', 'service_id', 'service_ids', 'service_lines', 'contract_type_id', 'is_custom'] as $field) {
            $this->postJson('/api/contracts', ['template_id' => $template->id, 'counterparty' => 'Party', $field => 1])
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        foreach (['customer', 'company'] as $slug) {
            $this->postJson('/api/contracts', ['template_id' => $this->template($slug)->id, 'counterparty' => 'Party'])->assertUnprocessable();
        }
        $this->postJson('/api/contracts', ['template_id' => $this->template('employment')->id])->assertUnprocessable();
        $this->postJson('/api/contracts', ['template_id' => $template->id])->assertUnprocessable();
        $this->assertDatabaseCount('contracts', 0);
    }

    public function test_custom_agreements_remain_available_and_cancellation_preserves_the_document(): void
    {
        $type = ContractType::create(['name' => 'Partnership', 'slug' => 'partnership', 'is_system' => false, 'is_active' => true]);
        $template = ContractTemplate::factory()->create(['contract_type_id' => $type->id, 'content' => '<p>Partnership terms</p>']);
        $id = $this->postJson('/api/contracts', ['template_id' => $template->id, 'counterparty' => 'Partner'])->assertCreated()->json('data.id');
        $original = Contract::findOrFail($id);
        $this->putJson('/api/contracts/'.$id.'/cancel')->assertOk();
        $cancelled = $original->fresh();
        $this->assertSame($original->contract_number, $cancelled->contract_number);
        $this->assertSame($original->content, $cancelled->content);
        $this->assertSame($original->snapshot_data, $cancelled->snapshot_data);
        $this->deleteJson('/api/contracts/'.$id)->assertUnprocessable();
    }

    public function test_staff_cannot_create_or_reassign_agreements_to_another_employees_case(): void
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'staff')->firstOrFail()->id]);
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $other = Employee::factory()->create();
        $foreign = Applicant::factory()->create(['responsible_employee_id' => $other->id]);
        $own = Applicant::factory()->create(['responsible_employee_id' => $employee->id]);
        $template = $this->template();
        Sanctum::actingAs($user, ['access']);
        $this->postJson('/api/contracts', [
            'template_id' => $template->id, 'applicant_id' => $foreign->id,
            'employee_id' => $employee->id, 'status' => 'draft',
        ])->assertForbidden();
        $this->assertDatabaseCount('contracts', 0);
        $id = $this->postJson('/api/contracts', [
            'template_id' => $template->id, 'applicant_id' => $own->id, 'status' => 'draft',
            'company_signatory_name' => 'Not the staff signer',
        ])->assertCreated()->json('data.id');
        $contract = Contract::findOrFail($id);
        $this->assertSame($employee->name, $contract->company_signatory_name);
        $this->putJson('/api/contracts/'.$id, ['applicant_id' => $foreign->id])->assertForbidden();
        $this->assertSame($own->id, $contract->fresh()->applicant_id);
        $this->assertSame($contract->content, $contract->fresh()->content);
    }
}
