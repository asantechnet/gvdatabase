<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\ContractType;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Employment and other agreements extend through the generic contract path:
 * a new record is linked to a live primary parent and issued as a draft. The
 * parent is never modified, cancellation keeps the whole record, and report
 * printing must never leak raw ISO timestamps.
 */
class ContractExtensionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'super_admin']));
        Sanctum::actingAs($user, ['access']);
    }

    public function test_employment_contract_extends_into_a_linked_draft(): void
    {
        $template = $this->employmentTemplate();
        $employee = Employee::factory()->create();

        $parent = $this->postJson('/api/contracts', [
            'template_id' => $template->id,
            'employee_id' => $employee->id,
            'status' => 'active',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
        ])->assertCreated()->json('data');

        $this->assertSame('primary', $parent['contract_role']);
        $this->assertNull($parent['parent_contract_id']);

        $extension = $this->postJson('/api/contracts', [
            'template_id' => $template->id,
            'employee_id' => $employee->id,
            // A submitted status is ignored: extensions are always drafts.
            'status' => 'active',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'parent_contract_id' => $parent['id'],
        ])->assertCreated();

        $extension->assertJsonPath('data.contract_role', 'extension')
            ->assertJsonPath('data.parent_contract_id', $parent['id'])
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.snapshot_data.extension_of', $parent['contract_number'])
            ->assertJsonPath('data.employee_id', $employee->id);

        // The parent keeps its own status and stays a primary agreement.
        $this->assertSame('primary', Contract::findOrFail($parent['id'])->contract_role);
        $this->assertSame('active', Contract::findOrFail($parent['id'])->status);
    }

    public function test_a_cancelled_contract_cannot_be_extended(): void
    {
        $template = $this->employmentTemplate();
        $employee = Employee::factory()->create();

        $parent = $this->postJson('/api/contracts', [
            'template_id' => $template->id,
            'employee_id' => $employee->id,
            'status' => 'active',
            'start_date' => '2025-01-01',
        ])->assertCreated()->json('data');

        $this->putJson("/api/contracts/{$parent['id']}/cancel")->assertOk();

        $this->postJson('/api/contracts', [
            'template_id' => $template->id,
            'employee_id' => $employee->id,
            'start_date' => '2026-01-01',
            'parent_contract_id' => $parent['id'],
        ])->assertUnprocessable()->assertJsonPath('code', 'business.contract_extension_invalid');
    }

    public function test_an_extension_contract_is_never_extended_again_and_types_must_match(): void
    {
        $template = $this->employmentTemplate();
        $employee = Employee::factory()->create();

        $parent = $this->postJson('/api/contracts', [
            'template_id' => $template->id,
            'employee_id' => $employee->id,
            'status' => 'active',
            'start_date' => '2025-01-01',
        ])->assertCreated()->json('data');

        $extension = $this->postJson('/api/contracts', [
            'template_id' => $template->id,
            'employee_id' => $employee->id,
            'start_date' => '2026-01-01',
            'parent_contract_id' => $parent['id'],
        ])->assertCreated()->json('data');

        // An extension is not a primary agreement, so it cannot be a parent.
        $this->postJson('/api/contracts', [
            'template_id' => $template->id,
            'employee_id' => $employee->id,
            'start_date' => '2027-01-01',
            'parent_contract_id' => $extension['id'],
        ])->assertUnprocessable()->assertJsonPath('code', 'business.contract_extension_invalid');

        // A parent of another contract type is rejected too.
        $otherType = ContractType::where('slug', 'other')->firstOrFail();
        $otherTemplate = ContractTemplate::create([
            'name' => 'Supply agreement', 'contract_type_id' => $otherType->id,
            'cover_text' => '', 'content' => '<p>Supply terms</p>',
        ]);
        $otherContract = $this->postJson('/api/contracts', [
            'template_id' => $otherTemplate->id,
            'counterparty' => 'ACME Trading',
            'status' => 'active',
            'start_date' => '2025-01-01',
        ])->assertCreated()->json('data');

        $this->postJson('/api/contracts', [
            'template_id' => $template->id,
            'employee_id' => $employee->id,
            'start_date' => '2026-01-01',
            'parent_contract_id' => $otherContract['id'],
        ])->assertUnprocessable()->assertJsonPath('code', 'business.contract_extension_invalid');
    }

    public function test_cancelled_contracts_keep_their_record_and_stay_listed(): void
    {
        $template = $this->employmentTemplate();
        $employee = Employee::factory()->create();

        $contract = $this->postJson('/api/contracts', [
            'template_id' => $template->id,
            'employee_id' => $employee->id,
            'status' => 'active',
            'start_date' => '2025-01-01',
        ])->assertCreated()->json('data');

        $this->putJson("/api/contracts/{$contract['id']}/cancel")
            ->assertOk()
            ->assertJsonPath('data.is_cancelled', true)
            ->assertJsonPath('data.status', 'cancelled');

        // The record is never deleted: it keeps its cancellation date and the
        // cancelled-contracts page is fed by no more than the flag.
        $stored = Contract::findOrFail($contract['id']);
        $this->assertTrue($stored->is_cancelled);
        $this->assertNotNull($stored->cancelled_at);

        $listed = $this->getJson('/api/contracts?only_cancelled=1')->assertOk()->json('data');
        $this->assertCount(1, $listed);
        $this->assertSame($contract['id'], $listed[0]['id']);
        $this->assertSame('cancelled', $listed[0]['status']);
        $this->assertNotNull($listed[0]['cancelled_at']);

        // It also remains in its own list, which is how the page shows it with
        // a cancelled label instead of removing it.
        $audience = $this->getJson('/api/contracts?audience=employees')->assertOk()->json('data');
        $this->assertCount(1, $audience);
        $this->assertTrue($audience[0]['is_cancelled']);
    }

    public function test_report_print_localizes_the_generated_at_date_and_period(): void
    {
        $response = $this->get('/api/reports/financial/export?format=print&start_date=2026-01-01&end_date=2026-01-31');

        $response->assertOk();
        $html = $response->getContent();

        // The raw machine timestamp and the raw `Y-m-d` filter values must not
        // reach the printed sheet.
        $this->assertDoesNotMatchRegularExpression('/Generated \d{4}-\d{2}-\d{2}T/', $html);
        $this->assertStringNotContainsString('2026-01-01', $html);
        $this->assertStringContainsString('GMT', $html);
        $this->assertStringContainsString('Jan 1, 2026', $html);
        $this->assertStringContainsString('Jan 31, 2026', $html);
    }

    public function test_locale_aware_report_dates_follow_the_request_language(): void
    {
        $query = 'format=print&start_date=2026-01-01&end_date=2026-01-31';

        // The SPA sends its own locale with the request...
        $explicit = $this->get("/api/reports/financial/export?{$query}&locale=fa-AF");
        $explicit->assertOk();
        $this->assertStringContainsString('dir="rtl"', $explicit->getContent());
        $this->assertStringNotContainsString('Jan 1, 2026', $explicit->getContent());
        $this->assertStringNotContainsString('2026-01-01', $explicit->getContent());

        // ...and `Accept-Language` is honored for clients that only send the header.
        $header = $this->withHeaders(['Accept-Language' => 'fa-AF'])->get("/api/reports/financial/export?{$query}");
        $header->assertOk();
        $this->assertStringContainsString('dir="rtl"', $header->getContent());
        $this->assertStringNotContainsString('2026-01-01', $header->getContent());

        // An unsupported tag never changes the language. (The previous header
        // is reset because the test client keeps default headers per test.)
        $fallback = $this->withHeaders(['Accept-Language' => 'en'])->get("/api/reports/financial/export?{$query}&locale=de-DE");
        $fallback->assertOk();
        $this->assertStringContainsString('Jan 1, 2026', $fallback->getContent());
    }

    public function test_visa_report_csv_exports_a_readable_generated_at(): void
    {
        $response = $this->get('/api/educational-reports/contracts/export?locale=en');
        $response->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('generated_at_utc,', $csv);
        $this->assertMatchesRegularExpression('/generated_at,"[^"]*GMT[^"]*"/', $csv);
        $this->assertStringNotContainsString("generated_at,2026", $csv);
    }

    public function test_editing_an_extension_preserves_its_parent_and_snapshot_origin(): void
    {
        $template = $this->employmentTemplate();
        $employee = Employee::factory()->create();

        $parent = $this->postJson('/api/contracts', [
            'template_id' => $template->id,
            'employee_id' => $employee->id,
            'status' => 'active',
            'start_date' => '2025-01-01',
        ])->assertCreated()->json('data');

        $otherParent = $this->postJson('/api/contracts', [
            'template_id' => $template->id,
            'employee_id' => $employee->id,
            'status' => 'active',
            'start_date' => '2025-02-01',
        ])->assertCreated()->json('data');

        $extension = $this->postJson('/api/contracts', [
            'template_id' => $template->id,
            'employee_id' => $employee->id,
            'start_date' => '2026-01-01',
            'parent_contract_id' => $parent['id'],
        ])->assertCreated()->json('data');

        // The parent key is create-only even if a stale client sends it. The
        // document rebuild must keep the original extension_of value.
        $this->putJson("/api/contracts/{$extension['id']}", [
            'template_id' => $template->id,
            'employee_id' => $employee->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'parent_contract_id' => $otherParent['id'],
        ])->assertOk()
            ->assertJsonPath('data.parent_contract_id', $parent['id'])
            ->assertJsonPath('data.snapshot_data.extension_of', $parent['contract_number']);

        $stored = Contract::findOrFail($extension['id']);
        $this->assertSame($parent['id'], $stored->parent_contract_id);
        $this->assertSame($parent['contract_number'], $stored->snapshot_data['extension_of']);
    }

    public function test_a_live_extension_blocks_a_second_renewal_and_cancellation_releases_it(): void
    {
        $template = $this->employmentTemplate();
        $employee = Employee::factory()->create();

        $parent = $this->postJson('/api/contracts', [
            'template_id' => $template->id,
            'employee_id' => $employee->id,
            'status' => 'active',
            'start_date' => '2025-01-01',
        ])->assertCreated()->json('data');

        $extension = $this->postJson('/api/contracts', [
            'template_id' => $template->id,
            'employee_id' => $employee->id,
            'start_date' => '2026-01-01',
            'parent_contract_id' => $parent['id'],
        ])->assertCreated()->json('data');

        $this->postJson('/api/contracts', [
            'template_id' => $template->id,
            'employee_id' => $employee->id,
            'start_date' => '2027-01-01',
            'parent_contract_id' => $parent['id'],
        ])->assertUnprocessable()->assertJsonPath('code', 'business.contract_renewal_exists');

        $this->assertSame(1, Contract::query()->where('parent_contract_id', $parent['id'])->count());

        $this->putJson("/api/contracts/{$extension['id']}/cancel")->assertOk();

        $this->postJson('/api/contracts', [
            'template_id' => $template->id,
            'employee_id' => $employee->id,
            'start_date' => '2028-01-01',
            'parent_contract_id' => $parent['id'],
        ])->assertCreated();

        $this->assertSame(2, Contract::query()->where('parent_contract_id', $parent['id'])->count());
    }

    public function test_extension_parties_must_match_the_parent_and_null_parties_remain_valid(): void
    {
        $template = $this->employmentTemplate();
        $employee = Employee::factory()->create();
        $otherEmployee = Employee::factory()->create();

        $parent = $this->postJson('/api/contracts', [
            'template_id' => $template->id,
            'employee_id' => $employee->id,
            'status' => 'active',
            'start_date' => '2025-01-01',
        ])->assertCreated()->json('data');

        $this->postJson('/api/contracts', [
            'template_id' => $template->id,
            'employee_id' => $otherEmployee->id,
            'start_date' => '2026-01-01',
            'parent_contract_id' => $parent['id'],
        ])->assertUnprocessable()->assertJsonPath('code', 'business.contract_extension_party_mismatch');

        $this->assertSame(0, Contract::query()->where('parent_contract_id', $parent['id'])->count());

        // Other agreements intentionally can have no employee/applicant. The
        // null party is a valid match when both parent and extension use it.
        $otherType = ContractType::where('slug', 'other')->firstOrFail();
        $otherTemplate = ContractTemplate::create([
            'name' => 'Supply agreement', 'contract_type_id' => $otherType->id,
            'cover_text' => '', 'content' => '<p>Supply terms</p>',
        ]);
        $otherParent = $this->postJson('/api/contracts', [
            'template_id' => $otherTemplate->id,
            'status' => 'active',
            'start_date' => '2025-01-01',
        ])->assertCreated()->json('data');

        $this->postJson('/api/contracts', [
            'template_id' => $otherTemplate->id,
            'start_date' => '2026-01-01',
            'parent_contract_id' => $otherParent['id'],
        ])->assertCreated()->assertJsonPath('data.contract_role', 'extension');
    }

    public function test_the_model_prevents_creating_an_extension_without_a_parent(): void
    {
        $template = $this->employmentTemplate();

        $this->expectException(ValidationException::class);

        Contract::create([
            'template_id' => $template->id,
            'contract_type_id' => $template->contract_type_id,
            'contract_role' => Contract::ROLE_EXTENSION,
            'status' => 'draft',
            'start_date' => '2026-01-01',
            'counterparty' => 'Test party',
        ]);
    }

    private function employmentTemplate(): ContractTemplate
    {
        $type = ContractType::where('slug', 'employment')->firstOrFail();

        return ContractTemplate::create([
            'name' => 'Employment agreement',
            'contract_type_id' => $type->id,
            'cover_text' => '',
            'content' => '<p>Employment terms for {{employee.name}}</p>',
        ]);
    }
}
