<?php

namespace Tests\Feature;

use App\Models\AcademyClass;
use App\Models\AcademyEnrollment;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Duration;
use App\Models\Income;
use App\Models\Language;
use App\Models\Program;
use App\Models\Teacher;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\Contracts\BuiltInContractTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AcademyContractTermTest extends TestCase
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
        $this->currency = Currency::factory()->create(['code' => 'AFN', 'rate' => 2.5, 'is_base' => false]);
        $this->language = Language::create(['name' => 'English', 'code' => 'en']);
        $this->program = Program::create(['name' => 'Foundation', 'is_active' => true]);
        $this->time = TimeSlot::create(['name' => 'Morning', 'is_active' => true]);
        $this->duration = Duration::create(['name' => 'Three months', 'months' => 3, 'is_active' => true]);
        $this->teacher = Teacher::create(['name' => 'Jane Doe', 'is_active' => true]);
        $template = app(BuiltInContractTemplateService::class)->ensure('academy');
        $this->version = $template->versions()->create([
            'language_id' => $this->language->id,
            'version_number' => 1,
            'name' => 'Academy terms',
            'status' => 'active',
            'is_current' => true,
            'current_scope_key' => "{$template->id}:0:{$this->language->id}",
            'content' => '<p>{{contract.number}} {{customer.name}}</p>',
        ]);
        $this->class = $this->makeClass();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeClass(array $overrides = []): AcademyClass
    {
        $class = AcademyClass::create([
            'name' => 'Class A',
            'program_id' => $this->program->id,
            'time_id' => $this->time->id,
            'duration_id' => $this->duration->id,
            'starts_on' => '2027-01-01',
            'ends_on' => null,
            'weekdays' => [1, 3],
            'starts_at' => '09:00:00',
            'ends_at' => '10:30:00',
            'capacity' => 20,
            'fee' => 120,
            'is_active' => true,
            ...$overrides,
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
            'currency_rate' => 2.5,
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

    private function assertNothingWritten(): void
    {
        $this->assertDatabaseCount('contracts', 0);
        $this->assertDatabaseCount('applicant_contract', 0);
        $this->assertDatabaseCount('academy_enrollments', 0);
        $this->assertDatabaseCount('incomes', 0);
        $this->assertDatabaseCount('students', 0);
    }

    public function test_class_end_wins_and_the_enrollment_uses_the_same_term(): void
    {
        $this->class->update(['ends_on' => '2027-02-01']);

        $contract = Contract::findOrFail(
            $this->postJson('/api/contracts/academy', $this->payload())->assertCreated()->json('data.id')
        );
        $line = $contract->applicantContracts()->sole();
        $enrollment = AcademyEnrollment::query()->where('applicant_contract_id', $line->id)->sole();

        $this->assertSame(now()->toDateString(), $contract->start_date->toDateString());
        $this->assertSame('2027-02-01', $contract->end_date->toDateString());
        $this->assertSame($contract->start_date->toDateString(), $enrollment->started_on->toDateString());
        $this->assertSame($contract->end_date->toDateString(), $enrollment->ends_on->toDateString());
        $this->assertSame('active', $enrollment->status);
        $this->assertSame($contract->id, $enrollment->contract_id);
        $this->assertSame($line->id, $enrollment->applicant_contract_id);
        $this->assertSame($this->class->id, $enrollment->class_id);

        $academy = $contract->snapshot_data['entity']['academy'];
        $this->assertSame($this->class->id, $academy['class_id']);
        $this->assertSame('Class A', $academy['class_name']);
        $this->assertSame('Foundation', $academy['program']);
        $this->assertSame('Morning', $academy['time']);
        $this->assertSame('Three months', $academy['duration']);
        $this->assertSame('STU-1001', $academy['student_id']);
        $this->assertSame(['Jane Doe'], $academy['teachers']);
        $this->assertSame($contract->start_date->toDateString(), $academy['started_on']);
        $this->assertSame('2027-02-01', $academy['ends_on']);
    }

    public function test_open_class_uses_calendar_month_arithmetic_at_month_end(): void
    {
        Carbon::setTestNow(Carbon::parse('2027-01-31 10:00:00'));

        $contract = Contract::findOrFail(
            $this->postJson('/api/contracts/academy', $this->payload())->assertCreated()->json('data.id')
        );

        $this->assertSame('2027-01-31', $contract->start_date->toDateString());
        $this->assertSame('2027-04-30', $contract->end_date->toDateString());
        $this->assertSame(
            '2027-04-30',
            AcademyEnrollment::where('contract_id', $contract->id)->sole()->ends_on->toDateString(),
        );
    }

    public function test_missing_duration_months_and_open_class_refuse_the_sale(): void
    {
        $this->duration->update(['months' => null]);

        $this->postJson('/api/contracts/academy', $this->payload())
            ->assertStatus(422)
            ->assertJsonPath('code', 'business.academy_term_undefined');

        $this->assertNothingWritten();
    }

    public function test_ended_class_refuses_the_sale_without_partial_rows(): void
    {
        $this->class->update(['ends_on' => '2026-01-01']);

        $this->postJson('/api/contracts/academy', $this->payload())
            ->assertStatus(422)
            ->assertJsonPath('code', 'business.academy_class_ended');

        $this->assertNothingWritten();
    }

    public function test_class_end_can_supply_the_term_when_the_duration_has_no_months(): void
    {
        $this->duration->update(['months' => null]);
        $this->class->update(['ends_on' => '2027-03-01']);

        $contract = Contract::findOrFail(
            $this->postJson('/api/contracts/academy', $this->payload())->assertCreated()->json('data.id')
        );

        $this->assertSame('2027-03-01', $contract->end_date->toDateString());
        $this->assertSame(1, AcademyEnrollment::where('contract_id', $contract->id)->count());
    }
}
