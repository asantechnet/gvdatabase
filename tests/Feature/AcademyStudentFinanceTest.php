<?php

namespace Tests\Feature;

use App\Models\AcademyClass;
use App\Models\AcademyEnrollment;
use App\Models\Applicant;
use App\Models\Currency;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\AcademyTestCase;

class AcademyStudentFinanceTest extends AcademyTestCase
{
    use RefreshDatabase;

    private Currency $currency;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
        $this->currency = Currency::factory()->create([
            'code' => 'AFN',
            'symbol' => '؋',
            'rate' => 1,
            'is_base' => true,
            'decimal_places' => 2,
        ]);
        $this->student = Student::factory()->create([
            'applicant_id' => null,
            'name' => 'Sara Student',
            'student_number' => 'STU-9001',
            'contact' => '0700999999',
        ]);
        $class = AcademyClass::factory()->create(['fee' => 500]);
        AcademyEnrollment::factory()->create([
            'student_id' => $this->student->id,
            'class_id' => $class->id,
            'status' => 'active',
        ]);
    }

    public function test_a_direct_student_income_is_recorded_tracked_and_receipted(): void
    {
        $created = $this->postJson('/api/incomes', [
            'student_id' => $this->student->id,
            'type' => 'Other',
            'description' => 'Academy tuition',
            'amount' => 150,
            'quantity' => 1,
            'currency_id' => $this->currency->id,
            'rate' => 1,
            'date' => now()->toDateString(),
        ])->assertCreated()
            ->assertJsonPath('data.applicant_id', null)
            ->assertJsonPath('data.student_id', $this->student->id)
            ->assertJsonPath('data.student.student_number', 'STU-9001')
            ->json('data');

        $this->assertNotNull($created['receipt_number']);

        $finance = $this->getJson('/api/academy/students/'.$this->student->id.'/finance')
            ->assertOk()
            ->assertJsonPath('data.student.name', 'Sara Student')
            ->assertJsonPath('data.incomes.0.id', $created['id'])
            ->assertJsonPath('data.incomes.0.receipt_number', $created['receipt_number'])
            ->json('data');

        $this->assertSame(150.0, (float) $finance['totals'][0]['amount']);
        $this->assertSame('AFN', $finance['totals'][0]['currency']['code']);

        $this->getJson('/api/incomes/'.$created['id'].'/receipt')
            ->assertOk()
            ->assertJsonPath('data.student.name', 'Sara Student')
            ->assertJsonPath('data.applicant', null)
            ->assertJsonPath('data.student.student_number', 'STU-9001');
    }

    public function test_an_applicant_payer_is_not_cleared_by_the_forms_null_student_field(): void
    {
        $applicant = Applicant::factory()->create(['name' => 'Contract Applicant']);
        $student = Student::factory()->create([
            'applicant_id' => $applicant->id,
            'student_number' => 'STU-9002',
        ]);

        $this->postJson('/api/incomes', [
            'applicant_id' => $applicant->id,
            'student_id' => null,
            'type' => 'Other',
            'description' => 'Applicant payment',
            'amount' => 75,
            'quantity' => 1,
            'currency_id' => $this->currency->id,
            'rate' => 1,
            'date' => now()->toDateString(),
        ])->assertCreated()
            ->assertJsonPath('data.applicant_id', $applicant->id)
            ->assertJsonPath('data.student_id', $student->id);
    }

    public function test_the_payer_picker_offers_direct_students_and_keeps_contract_pickers_separate(): void
    {
        $this->getJson('/api/incomes/payers?search=Sara')
            ->assertOk()
            ->assertJsonFragment([
                'key' => 'student:'.$this->student->id,
                'type' => 'student',
                'name' => 'Sara Student',
                'reference' => 'STU-9001',
            ]);

        // The contract-payment picker remains applicant-only; a direct student
        // has no applicant row and must not appear there.
        $this->getJson('/api/incomes/applicants?search=Sara')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
