<?php

namespace Tests\Feature;

use App\Models\AcademyClass;
use App\Models\AcademyEnrollment;
use App\Models\Applicant;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Duration;
use App\Models\Income;
use App\Models\Language;
use App\Models\Program;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\Contracts\BuiltInContractTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AcademyMultiCourseTest extends TestCase
{
    use RefreshDatabase;

    private Currency $currency;

    private Language $language;

    private Program $program;

    private TimeSlot $time;

    private Duration $duration;

    private Teacher $teacher;

    private AcademyClass $class;

    private \App\Models\ContractTemplateVersion $version;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
        Currency::factory()->create(['code' => 'USD', 'rate' => 1, 'is_base' => true]);
        $this->currency = Currency::factory()->create(['code' => 'AFN', 'rate' => 1, 'is_base' => false]);
        $this->language = Language::create(['name' => 'English', 'code' => 'en']);
        $this->program = Program::create(['name' => 'Foundation', 'is_active' => true]);
        $this->time = TimeSlot::create(['name' => 'Morning', 'is_active' => true]);
        $this->duration = Duration::create(['name' => 'Six months', 'months' => 6, 'is_active' => true]);
        $this->teacher = Teacher::create(['name' => 'Jane Doe', 'is_active' => true]);
        $template = app(BuiltInContractTemplateService::class)->ensure('academy');
        $this->version = $template->versions()->create([
            'language_id' => $this->language->id,
            'version_number' => 1,
            'name' => 'Academy terms',
            'status' => 'active',
            'is_current' => true,
            'current_scope_key' => "{$template->id}:0:{$this->language->id}",
            'content' => '<p>{{customer.name}}</p>',
        ]);
        $this->class = $this->makeClass('Class A');
    }

    private function makeClass(string $name): AcademyClass
    {
        $class = AcademyClass::create([
            'name' => $name,
            'program_id' => $this->program->id,
            'time_id' => $this->time->id,
            'duration_id' => $this->duration->id,
            'starts_on' => '2027-01-01',
            'ends_on' => null,
            'weekdays' => [1],
            'starts_at' => '09:00:00',
            'ends_at' => '10:30:00',
            'capacity' => 20,
            'fee' => 120,
            'is_active' => true,
        ]);
        $class->teachers()->sync([$this->teacher->id]);

        return $class;
    }

    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Academy applicant',
            'contact' => '0701234567',
            'fee' => 120,
            'discount' => 20,
            'currency_id' => $this->currency->id,
            'currency_rate' => 1,
            'language_id' => $this->language->id,
            'contract_template_version_id' => $this->version->id,
            'student_id' => 'STU-1001',
            'program_id' => $this->program->id,
            'time_id' => $this->time->id,
            'duration_id' => $this->duration->id,
            'teacher_ids' => [$this->teacher->id],
            'class_id' => $this->class->id,
            'service_fee' => 120,
            ...$overrides,
        ];
    }

    public function test_one_student_can_buy_a_second_course_without_overwriting_the_first(): void
    {
        $first = Contract::findOrFail(
            $this->postJson('/api/contracts/academy', $this->payload(['paid_fee' => 20]))
                ->assertCreated()->json('data.id')
        );
        $firstLine = $first->applicantContracts()->sole();
        $firstEnrollment = AcademyEnrollment::where('applicant_contract_id', $firstLine->id)->sole();
        $firstSnapshot = $first->snapshot_data;
        $firstEnd = $first->end_date->toDateString();
        $detailBefore = Applicant::findOrFail($first->applicant_id)->academyDetail->getAttributes();
        $income = Income::query()->sole();

        $secondClass = $this->makeClass('Class B');
        $extension = Contract::findOrFail(
            $this->postJson('/api/contracts/academy', $this->payload([
                'parent_contract_id' => $first->id,
                'class_id' => $secondClass->id,
                'service_fee' => 120,
            ]))->assertCreated()->assertJsonPath('data.contract_role', 'extension')->json('data.id')
        );

        $this->assertSame($first->applicant_id, $extension->applicant_id);
        $this->assertSame(1, Applicant::count());
        $this->assertSame(1, Student::count());
        $this->assertSame(2, Contract::count());
        $this->assertSame(2, AcademyEnrollment::count());
        $this->assertSame(1, Income::count());

        $this->assertSame($firstEnd, $first->fresh()->end_date->toDateString());
        $this->assertSame($firstSnapshot, $first->fresh()->snapshot_data);
        $this->assertSame($this->class->id, $firstEnrollment->fresh()->class_id);
        $this->assertSame(20, (int) $income->amount);
        $this->assertSame($firstLine->id, $income->applicant_contract_id);

        $extensionLine = $extension->applicantContracts()->sole();
        $extensionEnrollment = AcademyEnrollment::where('applicant_contract_id', $extensionLine->id)->sole();
        $this->assertSame($secondClass->id, $extensionEnrollment->class_id);
        $this->assertSame($extension->end_date->toDateString(), $extensionEnrollment->ends_on->toDateString());
        $this->assertSame($secondClass->id, $extension->snapshot_data['entity']['academy']['class_id']);
        $this->assertSame($extensionEnrollment->ends_on->toDateString(), $extension->snapshot_data['entity']['academy']['ends_on']);

        $this->assertSame(
            $detailBefore,
            Applicant::findOrFail($first->applicant_id)->academyDetail->getAttributes(),
            'The parent academy detail row must not be overwritten by a second course.',
        );
    }

    public function test_update_recomputes_the_term_without_duplicating_the_signing_receipt(): void
    {
        $contract = Contract::findOrFail(
            $this->postJson('/api/contracts/academy', $this->payload(['paid_fee' => 25]))
                ->assertCreated()->json('data.id')
        );
        $snapshot = $contract->snapshot_data;
        $income = Income::query()->sole();

        $this->putJson("/api/contracts/academy/{$contract->id}", $this->payload([
            'name' => 'Edited applicant',
            'discount' => 10,
        ]))->assertOk()->assertJsonPath('data.counterparty', 'Edited applicant');

        $this->assertSame(1, Income::count());
        $this->assertSame($income->id, Income::query()->sole()->id);
        $this->assertSame($snapshot, $contract->fresh()->snapshot_data);
        $this->assertSame(1, AcademyEnrollment::where('contract_id', $contract->id)->count());
    }
}
