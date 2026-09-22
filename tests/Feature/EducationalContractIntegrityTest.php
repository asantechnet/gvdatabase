<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ContractTemplate;
use App\Models\ContractType;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Language;
use App\Models\Role;
use App\Models\Scholarship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EducationalContractIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'super_admin']));
        Sanctum::actingAs($user, ['access']);
    }

    public function test_educational_contract_is_versioned_and_linked_to_its_case(): void
    {
        $type = ContractType::query()->where('slug', 'educational-service')->firstOrFail();
        $template = ContractTemplate::create(['name' => 'Student service agreement', 'contract_type_id' => $type->id, 'cover_text' => '', 'content' => '<p>Legacy copy</p>']);
        $language = Language::create(['name' => 'English', 'code' => 'en', 'is_active' => true]);
        $this->postJson("/api/contract-templates/{$template->id}/versions", ['language_id' => $language->id, 'content' => '<p>Educational agreement for {{customer.name}}</p>', 'status' => 'active'])->assertCreated();
        $customer = Customer::factory()->create();
        $case = $this->postJson('/api/applicants', ['customer_id' => $customer->id])->assertCreated()->json('data');

        $contract = $this->postJson('/api/contracts', ['template_id' => $template->id, 'customer_id' => $customer->id, 'applicant_id' => $case['id'], 'language_id' => $language->id, 'status' => 'draft', 'start_date' => now()->toDateString()])
            ->assertCreated()->assertJsonPath('data.applicant_id', $case['id'])->assertJsonPath('data.type_slug', 'educational-service')->json('data');

        $this->getJson('/api/applicants/'.$case['id'])->assertOk()->assertJsonPath('data.contracts.0.id', $contract['id']);
        $this->getJson('/api/contracts?audience=educational')->assertOk()->assertJsonPath('data.0.id', $contract['id']);
    }

    public function test_contract_and_applicant_links_cannot_cross_customers_and_case_activity_blocks_delete(): void
    {
        $type = ContractType::query()->where('slug', 'educational-service')->firstOrFail();
        $template = ContractTemplate::create(['name' => 'Student service agreement', 'contract_type_id' => $type->id, 'cover_text' => '', 'content' => '<p>Terms</p>']);
        $language = Language::create(['name' => 'English', 'code' => 'en', 'is_active' => true]);
        $this->postJson("/api/contract-templates/{$template->id}/versions", ['language_id' => $language->id, 'content' => '<p>Terms</p>', 'status' => 'active'])->assertCreated();
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();
        $case = Applicant::create(['customer_id' => $customer->id]);

        $this->postJson('/api/contracts', ['template_id' => $template->id, 'customer_id' => $otherCustomer->id, 'applicant_id' => $case->id, 'language_id' => $language->id, 'status' => 'draft', 'start_date' => now()->toDateString()])->assertUnprocessable()->assertJsonValidationErrors('applicant_id');

        Scholarship::create(['customer_id' => $customer->id, 'applicant_id' => $case->id, 'employee_id' => Employee::factory()->create()->id, 'currency_id' => Currency::factory()->create()->id, 'date' => now()->toDateString(), 'fee' => 100]);
        $this->deleteJson('/api/applicants/'.$case->id)->assertConflict()->assertJsonPath('code', 'business.applicant_has_activity')->assertJsonPath('blocking.scholarships', 1);
    }
}
