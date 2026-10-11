<?php

namespace Tests\Feature;

use App\Models\AcademyClass;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Duration;
use App\Models\Language;
use App\Models\Program;
use App\Models\Teacher;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\Contracts\BuiltInContractTemplateService;
use App\Support\BuiltInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AcademySlipTest extends TestCase
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

    private function activate(string $service): void
    {
        $template = app(BuiltInContractTemplateService::class)->ensure($service);
        $template->versions()->create([
            'language_id' => $this->language->id, 'country_id' => null,
            'version_number' => 1, 'name' => 'Agreed terms', 'status' => 'active',
            'is_current' => true, 'current_scope_key' => "{$template->id}:0:{$this->language->id}",
            'content' => '<p>Academy terms</p>',
        ]);
    }

    public function test_academy_slip_describes_enrolment_and_payment_state(): void
    {
        $this->activate(BuiltInService::ACADEMY);
        $program = Program::create(['name' => 'Web Development', 'is_active' => true]);
        $time = TimeSlot::create(['name' => 'Morning', 'is_active' => true]);
        $duration = Duration::create(['name' => '6 months', 'is_active' => true]);
        $class = AcademyClass::create(['name' => 'Class A', 'is_active' => true]);
        $teacher = Teacher::create(['name' => 'Jane Doe', 'is_active' => true]);

        $id = $this->postJson('/api/contracts/academy', [
            'name' => 'Academy applicant', 'contact' => '0701234567',
            'fee' => 120, 'discount' => 20, 'currency_id' => $this->currency->id,
            'currency_rate' => 2.5, 'language_id' => $this->language->id,
            'start_date' => '2026-10-08', 'service_fee' => 120,
            'program_id' => $program->id, 'time_id' => $time->id,
            'duration_id' => $duration->id, 'class_id' => $class->id,
            'teacher_ids' => [$teacher->id],
        ])->assertCreated()->json('data.id');

        $contract = Contract::findOrFail($id);
        $line = $contract->applicantContracts()->sole();
        $line->update(['amount_paid_overridden' => true, 'amount_paid' => 40]);

        $this->getJson("/api/contracts/{$id}/academy-slip")->assertOk()
            ->assertJsonPath('data.document', 'academy_slip')
            ->assertJsonPath('data.reference', $contract->contract_number)
            ->assertJsonPath('data.academy.program', 'Web Development')
            ->assertJsonPath('data.academy.time', 'Morning')
            ->assertJsonPath('data.academy.duration', '6 months')
            ->assertJsonPath('data.academy.class', 'Class A')
            ->assertJsonPath('data.academy.teachers', ['Jane Doe'])
            ->assertJsonPath('data.service.name', 'Academy')
            ->assertJsonPath('data.totals.total_fee', 100)
            ->assertJsonPath('data.totals.paid_amount', 40)
            ->assertJsonPath('data.totals.balance', 60);
    }

    public function test_academy_slip_rejects_a_non_academy_contract(): void
    {
        $this->activate(BuiltInService::TEST_OFFER);
        $id = $this->postJson('/api/contracts/test_offer', [
            'name' => 'Test applicant', 'fee' => 50, 'discount' => 0,
            'currency_id' => $this->currency->id, 'currency_rate' => 1,
            'language_id' => $this->language->id, 'start_date' => '2026-10-08',
            'booking_fee' => 50,
        ])->assertCreated()->json('data.id');

        $this->getJson("/api/contracts/{$id}/academy-slip")->assertNotFound();
    }
}
