<?php

namespace Tests\Feature;

use App\Models\AcademyClass;
use App\Models\AcademyEnrollment;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Duration;
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

class AcademyEnrollmentCapacityTest extends TestCase
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
        $this->class = $this->makeClass(['capacity' => 1]);
    }

    private function makeClass(array $overrides = []): AcademyClass
    {
        $class = AcademyClass::create([
            'name' => 'Class '.uniqid(),
            'program_id' => $this->program->id,
            'time_id' => $this->time->id,
            'duration_id' => $this->duration->id,
            'starts_on' => '2027-01-01',
            'ends_on' => null,
            'weekdays' => [1, 3],
            'starts_at' => '09:00:00',
            'ends_at' => '10:30:00',
            'capacity' => 1,
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
            'discount' => 0,
            'currency_id' => $this->currency->id,
            'currency_rate' => 1,
            'language_id' => $this->language->id,
            'contract_template_version_id' => $this->version->id,
            'student_id' => 'STU-'.uniqid(),
            'program_id' => $this->program->id,
            'time_id' => $this->time->id,
            'duration_id' => $this->duration->id,
            'teacher_ids' => [$this->teacher->id],
            'class_id' => $this->class->id,
            'service_fee' => 120,
            ...$overrides,
        ];
    }

    private function activeEnrollment(AcademyClass $class): AcademyEnrollment
    {
        return AcademyEnrollment::factory()->create([
            'class_id' => $class->id,
            'student_id' => Student::factory()->create(),
            'status' => 'active',
        ]);
    }

    public function test_full_class_refuses_a_new_sale_without_rows_or_income(): void
    {
        $first = Contract::findOrFail(
            $this->postJson('/api/contracts/academy', $this->payload())->assertCreated()->json('data.id')
        );
        $this->assertSame(1, AcademyEnrollment::where('class_id', $this->class->id)->where('status', 'active')->count());

        $this->postJson('/api/contracts/academy', $this->payload(['paid_fee' => 50]))
            ->assertStatus(422)
            ->assertJsonPath('code', 'business.academy_class_full');

        $this->assertSame(1, Contract::count());
        $this->assertSame(1, AcademyEnrollment::count());
        $this->assertDatabaseCount('incomes', 0);
        $this->assertSame(1, $first->applicantContracts()->count());
    }

    public function test_completed_withdrawn_and_cancelled_enrollments_do_not_consume_a_seat(): void
    {
        foreach (['completed', 'withdrawn', 'cancelled'] as $status) {
            $this->activeEnrollment($this->class)->update(['status' => $status, 'completed_on' => now()->toDateString()]);
        }

        $this->postJson('/api/contracts/academy', $this->payload())->assertCreated();

        $this->assertSame(1, AcademyEnrollment::where('class_id', $this->class->id)->where('status', 'active')->count());
    }

    public function test_current_enrollment_is_excluded_but_moving_to_another_full_class_is_refused(): void
    {
        $contract = Contract::findOrFail(
            $this->postJson('/api/contracts/academy', $this->payload())->assertCreated()->json('data.id')
        );
        $enrollment = AcademyEnrollment::where('contract_id', $contract->id)->sole();

        $this->putJson("/api/contracts/academy/{$contract->id}", $this->payload([
            'name' => 'Renamed applicant',
            'class_id' => $this->class->id,
        ]))->assertOk();

        $this->assertSame($this->class->id, $enrollment->fresh()->class_id);

        $other = $this->makeClass(['name' => 'Other full class']);
        $this->activeEnrollment($other);

        $this->putJson("/api/contracts/academy/{$contract->id}", $this->payload([
            'class_id' => $other->id,
        ]))->assertStatus(422)->assertJsonPath('code', 'business.academy_class_full');

        $this->assertSame($this->class->id, $enrollment->fresh()->class_id);
    }
}
