<?php

namespace Tests\Feature;

use App\Models\AcademyClass;
use App\Models\Applicant;
use App\Models\Contract;
use App\Models\ContractTemplateVersion;
use App\Models\ContractType;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Duration;
use App\Models\EducationLevel;
use App\Models\Employee;
use App\Models\Language;
use App\Models\Permission;
use App\Models\Program;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Skill;
use App\Models\Subject;
use App\Models\Task;
use App\Models\Teacher;
use App\Models\Test;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\Contracts\BuiltInContractTemplateService;
use App\Support\BuiltInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BuiltInContractGenerationTest extends TestCase
{
    use RefreshDatabase;

    private Currency $currency;

    private Language $language;

    private ?ContractTemplateVersion $activeVersion = null;

    private Country $defaultCountry;

    private Test $defaultTest;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
        Currency::factory()->create(['code' => 'USD', 'rate' => 1, 'is_base' => true]);
        $this->currency = Currency::factory()->create(['code' => 'AFN', 'rate' => 1, 'is_base' => false]);
        $this->language = Language::create(['name' => 'English', 'code' => 'en']);
        $this->defaultCountry = Country::create(['name' => 'Default destination', 'code' => 'QA']);
        $this->defaultTest = Test::create(['name' => 'IELTS', 'is_active' => true]);
    }

    private function activate(string $service): ContractTemplateVersion
    {
        $template = app(BuiltInContractTemplateService::class)->ensure($service);
        $countryId = in_array($service, [BuiltInService::SCHOLARSHIP, BuiltInService::PRIVATE], true) ? $this->defaultCountry->id : null;
        $testId = $service === BuiltInService::TEST_OFFER ? $this->defaultTest->id : null;

        return $this->activeVersion = $template->versions()->create([
            'language_id' => $this->language->id, 'country_id' => $countryId,
            'test_id' => $testId,
            'version_number' => 1, 'name' => 'Agreed terms', 'status' => 'active',
            'is_current' => true, 'current_scope_key' => implode(':', [$template->id, $countryId, $testId, $this->language->id]),
            'content' => '<p>{{contract.number}} {{customer.name}} {{service.name}} {{service.fee}} {{service.currency}} {{service.discount}}</p>',
        ]);
    }

    /**
     * A valid payload for one built-in service: the strict field sets mean each
     * service now needs its own catalogue references and itemised charges.
     */
    private function data(array $extra = [], string $service = BuiltInService::ACADEMY): array
    {
        $country = $this->defaultCountry;
        $education = EducationLevel::firstOrCreate(['name' => 'Bachelor'], ['is_active' => true]);
        $subject = Subject::firstOrCreate(['name' => 'Mathematics'], ['is_active' => true]);
        $semester = Semester::firstOrCreate(['code' => 'FALL'], ['name' => 'Fall', 'is_active' => true]);
        $test = $this->defaultTest;
        $skill = Skill::firstOrCreate(['name' => 'Writing'], ['is_active' => true]);
        $program = Program::firstOrCreate(['name' => 'Foundation'], ['is_active' => true]);
        $time = TimeSlot::firstOrCreate(['name' => 'Morning'], ['is_active' => true]);
        $duration = Duration::firstOrCreate(['name' => '6 months'], ['is_active' => true]);
        $class = AcademyClass::firstOrCreate(['name' => 'Class A'], ['is_active' => true]);
        $teacher = Teacher::firstOrCreate(['name' => 'Jane Doe'], ['is_active' => true]);
        $contractType = ContractType::firstOrCreate(['slug' => 'customer'], ['name' => 'Customer']);

        $fields = match ($service) {
            BuiltInService::SCHOLARSHIP => [
                'father_name' => 'Father', 'father_phone' => '0701111111',
                'grade_score' => 80, 'country_id' => $country->id,
                'education_level_id' => $education->id, 'favorite_subject_ids' => [$subject->id],
                'application_fee' => 120,
            ],
            BuiltInService::PRIVATE => [
                'father_name' => 'Father', 'father_phone' => '0701111111',
                'grade_score' => 80, 'country_id' => $country->id,
                'education_level_id' => $education->id, 'semester_id' => $semester->id,
                'contract_type_id' => $contractType->id, 'favorite_subject_ids' => [$subject->id],
                'university_fee' => 0, 'application_fee_min' => 60,
                'application_fee_max' => 100, 'visa_fee' => 20,
            ],
            BuiltInService::TEST_OFFER => [
                'backup_phone' => '0702222222', 'test_id' => $test->id,
                'skill_ids' => [$skill->id], 'booking_fee' => 50,
                'service_fee' => 50, 'remote_fee' => 20,
            ],
            default => [
                'student_id' => 'STU-1001', 'program_id' => $program->id,
                'time_id' => $time->id, 'duration_id' => $duration->id,
                'teacher_ids' => [$teacher->id], 'class_id' => $class->id,
                'service_fee' => 120,
            ],
        };

        return [...[
            'name' => 'New applicant', 'contact' => '0701234567',
            'fee' => 120, 'discount' => 20, 'currency_id' => $this->currency->id,
            'currency_rate' => 2.5, 'language_id' => $this->language->id,
            ...($this->activeVersion ? ['contract_template_version_id' => $this->activeVersion->id] : []),
        ], ...$fields, ...$extra];
    }

    public function test_contract_numbers_use_the_compact_customer_format(): void
    {
        $contract = Contract::factory()->create();

        $this->assertMatchesRegularExpression(
            '/^CTR-'.now()->format('y').'-\\d{3}$/',
            $contract->contract_number,
        );
    }

    public function test_each_service_atomically_creates_identity_details_contract_line_and_task(): void
    {
        foreach (BuiltInService::ALL as $service) {
            $this->activate($service);
            $response = $this->postJson("/api/contracts/{$service}", $this->data([], $service))
                ->assertCreated()->assertJsonPath('data.service', $service)
                ->assertJsonPath('data.contract_role', 'primary')
                ->assertJsonPath('data.services.0.built_in_service', $service)
                ->assertJsonPath('data.services.0.currency_rate', '2.50000000');
            $contract = Contract::findOrFail($response->json('data.id'));
            $this->assertSame(now()->toDateString(), $contract->start_date->toDateString());
            $applicant = $contract->applicant;
            $this->assertSame($service, $applicant->service);
            $this->assertNotNull($applicant->detail());
            $line = $contract->applicantContracts()->sole();
            $this->assertArrayNotHasKey('service_id', $line->getAttributes());
            $this->assertSame(100.0, $line->remainingTotal());
            $task = Task::where('applicant_contract_id', $line->id)->sole();
            $this->assertSame($applicant->id, $task->applicant_id);
            $this->assertTrue($task->users->contains('id', auth()->id()));
            $this->assertStringContainsString($contract->contract_number, $contract->content);
            $this->assertStringContainsString('New applicant', $contract->content);
            $this->assertStringNotContainsString('{{', $contract->content);
        }
        $this->assertSame(count(BuiltInService::ALL), Applicant::count());
        $this->assertSame(count(BuiltInService::ALL), Task::count());
        $this->assertEquals(2.5, $this->currency->fresh()->rate);
        $this->assertFalse(Schema::hasTable('services'));
        $this->assertFalse(Schema::hasTable('packages'));
    }

    public function test_each_service_contract_updates_its_live_case_and_terms_without_rewriting_the_issued_snapshot(): void
    {
        foreach (BuiltInService::ALL as $service) {
            $this->activate($service);
            $created = $this->postJson("/api/contracts/{$service}", $this->data([], $service))->assertCreated()->json('data');
            $issued = Contract::findOrFail($created['id']);
            $content = $issued->content;
            $snapshot = $issued->snapshot_data;

            $charges = match ($service) {
                BuiltInService::SCHOLARSHIP => ['application_fee' => 150],
                BuiltInService::PRIVATE => ['application_fee_min' => 80, 'application_fee_max' => 120, 'visa_fee' => 30],
                BuiltInService::TEST_OFFER => ['booking_fee' => 50, 'service_fee' => 70, 'remote_fee' => 30],
                default => ['service_fee' => 150],
            };
            $payload = $this->data([
                'name' => 'Edited '.$service,
                'fee' => 150,
                'discount' => 10,
                'currency_rate' => 3,
                ...$charges,
            ], $service);

            $this->putJson("/api/contracts/{$service}/{$issued->id}", $payload)
                ->assertOk()
                ->assertJsonPath('data.service', $service)
                ->assertJsonPath('data.counterparty', 'Edited '.$service);

            $updated = $issued->fresh();
            $this->assertSame($content, $updated->content);
            $this->assertSame($snapshot, $updated->snapshot_data);
            $this->assertSame('Edited '.$service, $updated->applicant->name);
            $this->assertEquals(150, $updated->applicantContracts()->sole()->fee);
            $this->assertEquals(10, $updated->applicantContracts()->sole()->discount);
        }
    }

    public function test_missing_draft_archived_and_wrong_language_versions_cannot_be_issued(): void
    {
        $this->postJson('/api/contracts/academy', $this->data())->assertUnprocessable();
        $version = $this->activate('academy');
        foreach (['draft', 'archived'] as $status) {
            $version->update(['status' => $status]);
            $this->postJson('/api/contracts/academy', $this->data())->assertUnprocessable();
        }
        $version->update(['status' => 'active']);
        $other = Language::create(['name' => 'Persian', 'code' => 'fa']);
        $this->postJson('/api/contracts/academy', $this->data(['language_id' => $other->id]))->assertUnprocessable();
        $this->assertDatabaseCount('applicants', 0);
        $this->assertDatabaseCount('contracts', 0);
        $this->assertDatabaseCount('tasks', 0);
        $this->assertEquals(1, $this->currency->fresh()->rate);
    }

    public function test_an_active_historical_version_can_be_selected_explicitly(): void
    {
        $historical = $this->activate('academy');
        $historical->update(['is_current' => false, 'current_scope_key' => null]);
        $template = $historical->template;
        $template->versions()->create([
            'language_id' => $this->language->id,
            'country_id' => null,
            'version_number' => 2,
            'name' => 'Current terms',
            'status' => 'active',
            'is_current' => true,
            'current_scope_key' => "{$template->id}:0:{$this->language->id}",
            'content' => '<p>{{contract.number}} {{customer.name}}</p>',
        ]);

        $this->postJson('/api/contracts/academy', $this->data([
            'contract_template_version_id' => $historical->id,
        ]))->assertCreated()->assertJsonPath('data.contract_template_version_id', $historical->id);
    }

    public function test_catalogue_and_cross_service_fields_are_rejected(): void
    {
        foreach (['applicant_id' => 1, 'service_id' => 1, 'package_id' => 1, 'test_id' => 1, 'amount_paid' => 100, 'status' => 'draft'] as $field => $value) {
            $this->postJson('/api/contracts/academy', $this->data([$field => $value]))
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->postJson('/api/contracts/unknown', $this->data())->assertStatus(405);
        $this->assertDatabaseCount('applicants', 0);
    }

    public function test_service_contract_rejects_another_services_detail_fields(): void
    {
        $this->activate('scholarship');
        $this->postJson('/api/contracts/scholarship', $this->data([
            'test_id' => 1,
            'skill_ids' => [1],
        ], BuiltInService::SCHOLARSHIP))->assertUnprocessable()->assertJsonValidationErrors(['test_id', 'skill_ids']);
    }

    public function test_generic_creation_cannot_bypass_the_builtin_command(): void
    {
        $version = $this->activate('academy');
        $applicant = Applicant::factory()->create(['service' => 'academy']);
        $this->seedApplicantDocuments($applicant);
        $this->postJson('/api/contracts', [
            'template_id' => $version->contract_template_id, 'applicant_id' => $applicant->id,
            'start_date' => '2026-10-08', 'status' => 'active',
        ])->assertUnprocessable();
        $this->assertDatabaseCount('contracts', 0);
    }

    public function test_invalid_money_identity_and_scope_are_rejected_without_side_effects(): void
    {
        foreach ([['name' => ''], ['discount' => 121], ['currency_rate' => 0], ['fee' => -1], ['fee' => 1.001], ['language_id' => 99999], ['start_date' => '2026-10-08']] as $change) {
            $this->postJson('/api/contracts/test_offer', $this->data($change, BuiltInService::TEST_OFFER))->assertUnprocessable();
        }
        $this->assertDatabaseCount('applicants', 0);
    }

    public function test_extension_reuses_identity_and_freezes_new_economics_without_editing_primary(): void
    {
        $this->activate('academy');
        $id = $this->postJson('/api/contracts/academy', $this->data())->assertCreated()->json('data.id');
        $primary = Contract::findOrFail($id);
        $content = $primary->content;
        // An academy extension carries the (editable) identity; it still reuses
        // the same applicant and freezes its own economics.
        $extension = $this->data(['parent_contract_id' => $id, 'fee' => 200, 'service_fee' => 200]);
        $this->postJson('/api/contracts/academy', $extension)->assertCreated()
            ->assertJsonPath('data.contract_role', 'extension')->assertJsonPath('data.parent_contract_id', $id)
            ->assertJsonPath('data.applicant_id', $primary->applicant_id);
        $this->assertDatabaseCount('applicants', 1);
        $this->assertDatabaseCount('contracts', 2);
        $this->assertDatabaseCount('tasks', 2);
        $this->assertSame($content, $primary->fresh()->content);
        $this->postJson('/api/contracts/test_offer', $extension)->assertUnprocessable();
        $primary->update(['is_cancelled' => true, 'status' => 'cancelled']);
        $this->postJson('/api/contracts/academy', $extension)->assertUnprocessable();
    }

    public function test_academy_extension_applies_edited_applicant_identity(): void
    {
        $this->activate('academy');
        $id = $this->postJson('/api/contracts/academy', $this->data())->assertCreated()->json('data.id');
        $applicantId = Contract::findOrFail($id)->applicant_id;

        $this->postJson('/api/contracts/academy', $this->data([
            'parent_contract_id' => $id,
            'name' => 'Renamed applicant',
            'contact' => '0709999999',
            'address' => 'New address',
            'notes' => 'Updated during extension',
            'fee' => 150,
            'service_fee' => 150,
        ]))->assertCreated()->assertJsonPath('data.applicant_id', $applicantId);

        $this->assertDatabaseCount('applicants', 1);
        $applicant = Applicant::findOrFail($applicantId);
        $this->assertSame('Renamed applicant', $applicant->name);
        $this->assertSame('0709999999', $applicant->contact);
        $this->assertSame('New address', $applicant->address);
        $this->assertSame('Updated during extension', $applicant->notes);

        // Every other service still copies the parent applicant and rejects edits.
        $this->activate('test_offer');
        $testOfferId = $this->postJson('/api/contracts/test_offer', $this->data([], BuiltInService::TEST_OFFER))->assertCreated()->json('data.id');
        $this->postJson('/api/contracts/test_offer', $this->data(['parent_contract_id' => $testOfferId, 'name' => 'Sneaky change'], BuiltInService::TEST_OFFER))
            ->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_render_failure_rolls_back_currency_rate_identity_line_and_audit_writes(): void
    {
        $this->activate('academy')->update(['content' => '<p>{{unknown.value}}</p>']);
        $this->postJson('/api/contracts/academy', $this->data())->assertUnprocessable();
        $this->assertDatabaseCount('applicants', 0);
        $this->assertDatabaseCount('contracts', 0);
        $this->assertDatabaseCount('applicant_contract', 0);
        $this->assertDatabaseCount('tasks', 0);
        $this->assertEquals(1, $this->currency->fresh()->rate);
    }

    public function test_creation_requires_contract_permission(): void
    {
        $role = Role::firstOrCreate(['name' => 'contract_reader']);
        $role->permissions()->sync(Permission::where('name', 'view_contracts')->pluck('id'));
        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);
        Sanctum::actingAs($user, ['access']);
        $this->postJson('/api/contracts/academy', $this->data())->assertForbidden();
        $this->assertDatabaseCount('applicants', 0);
    }

    public function test_country_scope_never_falls_back_to_another_country_or_generic_terms(): void
    {
        $version = $this->activate('scholarship');
        $country = Country::create(['name' => 'Destination', 'code' => 'DS']);
        $this->postJson('/api/contracts/scholarship', $this->data(['country_id' => $country->id], BuiltInService::SCHOLARSHIP))->assertUnprocessable();
        $version->update(['country_id' => $country->id]);
        $id = $this->postJson('/api/contracts/scholarship', $this->data(['country_id' => $country->id], BuiltInService::SCHOLARSHIP))
            ->assertCreated()->assertJsonPath('data.contract_country_id', $country->id)->json('data.id');
        $this->assertSame($country->id, Contract::findOrFail($id)->applicant->scholarshipDetail->country_id);
        $other = Country::create(['name' => 'Other destination', 'code' => 'OD']);
        $this->postJson('/api/contracts/scholarship', $this->data(['country_id' => $other->id], BuiltInService::SCHOLARSHIP))->assertUnprocessable();
        $this->assertDatabaseCount('applicants', 1);
    }

    public function test_builtin_line_accepts_a_payment_and_printable_receipt_without_a_catalogue_row(): void
    {
        $this->activate('test_offer');
        $id = $this->postJson('/api/contracts/test_offer', $this->data([], BuiltInService::TEST_OFFER))->assertCreated()->json('data.id');
        $line = Contract::findOrFail($id)->applicantContracts()->sole();
        $incomeId = $this->postJson('/api/incomes', [
            'applicant_contract_id' => $line->id, 'type' => 'Service', 'amount' => 40,
            'quantity' => 1, 'currency_id' => $this->currency->id, 'rate' => 2.5, 'date' => '2026-10-08',
        ])->assertCreated()->json('data.id');
        $this->assertEquals(40, $line->fresh()->amount_paid);
        $this->assertSame('partially_paid', $line->fresh()->payment_status);
        $this->getJson("/api/incomes/{$incomeId}/receipt")->assertOk();
        $this->getJson('/api/contracts?service=test_offer')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/contracts?service=academy')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_staff_options_and_creation_are_scoped_without_currency_management_permission(): void
    {
        $this->activate('academy');
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'staff')->firstOrFail()->id]);
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $other = Employee::factory()->create();
        Sanctum::actingAs($user, ['access']);
        $this->getJson('/api/contracts/academy/options')->assertOk()
            ->assertJsonCount(1, 'data.employees')->assertJsonPath('data.employees.0.id', $employee->id);
        $this->postJson('/api/contracts/academy', $this->data(['responsible_employee_id' => $other->id]))->assertForbidden();
        $id = $this->postJson('/api/contracts/academy', $this->data())->assertCreated()->json('data.id');
        $contract = Contract::findOrFail($id);
        $this->assertSame($employee->id, $contract->applicant->responsible_employee_id);
        $this->assertTrue(Task::where('applicant_id', $contract->applicant_id)->sole()->users->contains('id', $user->id));
        $foreign = Contract::factory()->create(['service' => 'academy', 'applicant_id' => Applicant::factory()->create(['responsible_employee_id' => $other->id, 'service' => 'academy'])->id, 'contract_role' => 'primary', 'status' => 'active', 'is_cancelled' => false]);
        $extension = $this->data(['parent_contract_id' => $foreign->id]);
        $this->postJson('/api/contracts/academy', $extension)->assertForbidden();
    }
}
