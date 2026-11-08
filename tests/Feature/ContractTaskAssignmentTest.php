<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Country;
use App\Models\Currency;
use App\Models\EducationLevel;
use App\Models\Language;
use App\Models\Skill;
use App\Models\Subject;
use App\Models\Task;
use App\Models\Test;
use App\Models\User;
use App\Services\Contracts\BuiltInContractTemplateService;
use App\Services\ContractTaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContractTaskAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function createContract(string $service): Contract
    {
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
        $currency = Currency::factory()->create(['rate' => 1]);
        $language = Language::create(['name' => 'English', 'code' => 'en']);

        // Built-in services require their own catalogue references and
        // itemised charges before a contract (and its delivery task) exists.
        $country = null;
        $test = null;
        $skill = null;
        if ($service === 'test_offer') {
            $test = Test::create(['name' => 'IELTS', 'is_active' => true]);
            $skill = Skill::firstOrCreate(['name' => 'Writing'], ['is_active' => true]);
        } else {
            $country = Country::create(['name' => 'Destination', 'code' => 'QA']);
        }

        $template = app(BuiltInContractTemplateService::class)->ensure($service);
        $version = $template->versions()->create([
            'language_id' => $language->id,
            'country_id' => $country?->id,
            'test_id' => $test?->id,
            'version_number' => 1, 'name' => 'Terms',
            'status' => 'active', 'is_current' => true,
            'current_scope_key' => implode(':', [$template->id, $country?->id ?? 0, $test?->id ?? 0, $language->id]),
            'content' => '<p>{{customer.name}}</p>',
        ]);

        $fields = $service === 'test_offer'
            ? [
                'backup_phone' => '0700000000',
                'test_id' => $test->id,
                'skill_ids' => [$skill->id],
                'booking_fee' => 100,
                'service_fee' => 300,
                'remote_fee' => 100,
            ]
            : [
                'grade_score' => 80,
                'country_id' => $country->id,
                'education_level_id' => EducationLevel::firstOrCreate(['name' => 'Bachelor'], ['is_active' => true])->id,
                'favorite_subject_ids' => [Subject::firstOrCreate(['name' => 'Mathematics'], ['is_active' => true])->id],
                'application_fee' => 500,
            ];

        $id = $this->postJson('/api/contracts/'.$service, [
            ...$fields,
            'name' => 'Task Applicant', 'contact' => '0700000000',
            'fee' => 500, 'discount' => 0, 'currency_id' => $currency->id,
            'currency_rate' => 1, 'language_id' => $language->id,
            'contract_template_version_id' => $version->id,
        ])->assertCreated()->json('data.id');

        return Contract::findOrFail($id);
    }

    public function test_builtin_creation_assigns_the_task_to_the_exact_applicant_contract_line(): void
    {
        $contract = $this->createContract('scholarship');
        $line = $contract->applicantContracts()->sole();
        $task = Task::where('applicant_contract_id', $line->id)->sole();
        $this->assertSame($contract->applicant_id, $task->applicant_id);
        $this->assertStringContainsString('Task Applicant', $task->title);
        $this->assertStringContainsString('Scholarship', $task->title);
        $this->assertNull($task->description, 'Contract financial detail must stay off the task.');
        $this->assertArrayNotHasKey('service_id', $line->getAttributes());
    }

    public function test_the_same_deliverable_is_never_given_two_open_tasks(): void
    {
        $contract = $this->createContract('test_offer');
        $line = $contract->applicantContracts()->sole();
        app(ContractTaskService::class)->ensureForApplicantContract($line);
        $this->assertSame(1, Task::where('applicant_contract_id', $line->id)->count());
    }
}
