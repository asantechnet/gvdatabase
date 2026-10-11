<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Currency;
use App\Models\Language;
use App\Models\Task;
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
        $template = app(BuiltInContractTemplateService::class)->ensure($service);
        $template->versions()->create([
            'language_id' => $language->id, 'version_number' => 1, 'name' => 'Terms',
            'status' => 'active', 'is_current' => true,
            'current_scope_key' => "{$template->id}:0:{$language->id}",
            'content' => '<p>{{customer.name}}</p>',
        ]);
        $id = $this->postJson('/api/contracts/'.$service, [
            'name' => 'Task Applicant', 'fee' => 500, 'discount' => 0, 'currency_id' => $currency->id,
            'currency_rate' => 1, 'language_id' => $language->id, 'start_date' => now()->toDateString(),
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
