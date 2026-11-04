<?php

namespace Tests\Feature;

use App\Models\AcademyClass;
use App\Models\ApplicantStage;
use App\Models\Contract;
use App\Models\ContractTemplateVersion;
use App\Models\Currency;
use App\Models\Duration;
use App\Models\Language;
use App\Models\Program;
use App\Models\Task;
use App\Models\Teacher;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\Contracts\BuiltInContractTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApplicantContractStageTest extends TestCase
{
    use RefreshDatabase;

    private Currency $currency;

    private Language $language;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
        Currency::factory()->create(['code' => 'USD', 'rate' => 1, 'is_base' => true]);
        $this->currency = Currency::factory()->create(['code' => 'AFN', 'rate' => 1, 'is_base' => false]);
        $this->language = Language::create(['name' => 'English', 'code' => 'en']);
    }

    private function activate(string $service): ContractTemplateVersion
    {
        $template = app(BuiltInContractTemplateService::class)->ensure($service);

        return $template->versions()->create([
            'language_id' => $this->language->id, 'country_id' => null,
            'version_number' => 1, 'name' => 'Agreed terms', 'status' => 'active',
            'is_current' => true, 'current_scope_key' => "{$template->id}:0:{$this->language->id}",
            'content' => '<p>{{contract.number}} {{customer.name}}</p>',
        ]);
    }

    private function createAcademyContract(): Contract
    {
        $version = $this->activate('academy');
        $id = $this->postJson('/api/contracts/academy', [
            'name' => 'Stage applicant', 'contact' => '0701234567',
            'fee' => 120, 'discount' => 0, 'currency_id' => $this->currency->id,
            'currency_rate' => 1, 'language_id' => $this->language->id,
            'contract_template_version_id' => $version->id,
            'student_id' => 'STU-100', 'service_fee' => 120,
            'program_id' => Program::create(['name' => 'Foundation', 'is_active' => true])->id,
            'time_id' => TimeSlot::create(['name' => 'Morning', 'is_active' => true])->id,
            'duration_id' => Duration::create(['name' => '6 months', 'is_active' => true])->id,
            'teacher_ids' => [Teacher::create(['name' => 'Jane Doe', 'is_active' => true])->id],
            'class_id' => AcademyClass::create(['name' => 'Class A', 'is_active' => true])->id,
        ])->assertCreated()->json('data.id');

        return Contract::findOrFail($id);
    }

    private function stageId(string $service, string $key): int
    {
        return ApplicantStage::query()->where('service', $service)->where('key', $key)->firstOrFail()->id;
    }

    public function test_each_service_owns_its_stage_list(): void
    {
        foreach (['scholarship', 'test_offer', 'academy', 'private'] as $service) {
            $this->assertGreaterThan(0, ApplicantStage::where('service', $service)->count(), "Missing stages for {$service}");
        }
    }

    public function test_contract_line_starts_at_its_service_new_stage(): void
    {
        $contract = $this->createAcademyContract();
        $line = $contract->applicantContracts()->sole();

        $this->assertSame($this->stageId('academy', 'new'), $line->current_stage_id);
        $this->assertSame('new', $line->currentStage->key);
    }

    public function test_line_stage_move_updates_history_and_mirrors_the_applicant(): void
    {
        $contract = $this->createAcademyContract();
        $line = $contract->applicantContracts()->sole();

        $this->postJson("/api/applicant-contracts/{$line->id}/change-stage", [
            'stage_id' => $this->stageId('academy', 'enrolled'), 'note' => 'Enrolled',
        ])->assertOk()->assertJsonPath('data.current_stage.key', 'enrolled');

        $this->assertDatabaseHas('applicant_stage_histories', [
            'applicant_contract_id' => $line->id,
            'new_stage_id' => $this->stageId('academy', 'enrolled'),
        ]);
        // The applicant mirrors its most recently changed line.
        $this->assertSame($this->stageId('academy', 'enrolled'), $contract->applicant->fresh()->current_stage_id);
    }

    public function test_stage_from_another_service_is_rejected(): void
    {
        $contract = $this->createAcademyContract();
        $line = $contract->applicantContracts()->sole();

        $this->postJson("/api/applicant-contracts/{$line->id}/change-stage", [
            'stage_id' => $this->stageId('scholarship', 'consultation'),
        ])->assertStatus(422);
    }

    public function test_illegal_jump_is_rejected_but_forward_path_is_allowed(): void
    {
        $contract = $this->createAcademyContract();
        $line = $contract->applicantContracts()->sole();

        // new → in_class is a skip; not permitted by the seeded academy edges.
        $this->postJson("/api/applicant-contracts/{$line->id}/change-stage", [
            'stage_id' => $this->stageId('academy', 'in_class'),
        ])->assertStatus(422);

        $this->postJson("/api/applicant-contracts/{$line->id}/change-stage", [
            'stage_id' => $this->stageId('academy', 'enrolled'),
        ])->assertOk();
    }

    public function test_terminal_stage_completes_the_linked_task(): void
    {
        $contract = $this->createAcademyContract();
        $line = $contract->applicantContracts()->sole();

        foreach (['enrolled', 'in_class', 'completed'] as $key) {
            $this->postJson("/api/applicant-contracts/{$line->id}/change-stage", [
                'stage_id' => $this->stageId('academy', $key),
                'note' => "Moved to {$key}",
            ])->assertOk();
        }

        $task = Task::where('applicant_contract_id', $line->id)->sole();
        $this->assertSame(100, (int) $task->progress);
        $this->assertNotNull($task->completed_at);
        $this->assertSame('closed', $contract->applicant->fresh()->status);
    }
}
