<?php

namespace Tests\Feature;

use App\Models\AcademyCertificate;
use App\Models\AcademyCertificateTemplate;
use App\Models\AcademyClass;
use App\Models\AcademyEnrollment;
use App\Models\Currency;
use App\Models\Permission;
use App\Models\Program;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\AcademyTestCase;

class AcademyCertificateTest extends AcademyTestCase
{
    use RefreshDatabase;

    private AcademyClass $class;

    private AcademyCertificateTemplate $template;

    private function enrollment(string $status = 'completed'): AcademyEnrollment
    {
        return AcademyEnrollment::factory()->create([
            'student_id' => Student::factory()->create(),
            'class_id' => $this->class->id,
            'started_on' => '2027-01-04',
            'ends_on' => '2027-03-31',
            'completed_on' => '2027-03-31',
            'status' => $status,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
        Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $program = Program::create(['name' => 'Foundation', 'is_active' => true]);
        $this->class = AcademyClass::create([
            'name' => 'Class A', 'program_id' => $program->id,
            'starts_on' => '2027-01-01', 'ends_on' => '2027-03-31',
            'weekdays' => [1, 3], 'capacity' => 20, 'is_active' => true,
        ]);
        $this->template = AcademyCertificateTemplate::create([
            'name' => 'Default', 'title' => 'Certificate of Completion',
            'body' => 'Awarded to {student_name} ({student_id}) for {program}.',
            'signatory_title' => 'Director', 'is_active' => true,
        ]);
    }

    public function test_completed_enrollment_issues_numbered_snapshot_and_duplicate_is_refused(): void
    {
        $enrollment = $this->enrollment();
        $response = $this->postJson('/api/academy/certificates', ['academy_enrollment_id' => $enrollment->id])
            ->assertCreated()
            ->assertJsonPath('data.status', 'issued')
            ->assertJsonPath('data.snapshot.award.student_name', $enrollment->student->name)
            ->assertJsonPath('data.snapshot.template.signatory_title', 'Director');
        $number = $response->json('data.certificate_number');
        $this->assertMatchesRegularExpression('/^CERT-\d{4}-\d{6}$/', $number);
        $this->assertDatabaseCount('academy_certificates', 1);

        $this->postJson('/api/academy/certificates', ['academy_enrollment_id' => $enrollment->id])
            ->assertUnprocessable()->assertJsonValidationErrors('academy_enrollment_id');
        $this->assertDatabaseCount('academy_certificates', 1);
        $this->assertSame($number, AcademyCertificate::sole()->certificate_number);
    }

    public function test_non_completed_enrollment_is_refused_without_a_row_or_number(): void
    {
        $enrollment = $this->enrollment('active');
        $this->postJson('/api/academy/certificates', ['academy_enrollment_id' => $enrollment->id])
            ->assertUnprocessable()->assertJsonValidationErrors('academy_enrollment_id');
        $this->assertDatabaseCount('academy_certificates', 0);
    }

    public function test_completed_enrollment_without_an_applicant_issues_a_certificate(): void
    {
        // A direct Academy student has no applicant and no contract; the
        // certificate must still be issued from the durable student record.
        $student = Student::factory()->create(['applicant_id' => null]);
        $enrollment = AcademyEnrollment::factory()->create([
            'student_id' => $student->id,
            'applicant_id' => null,
            'class_id' => $this->class->id,
            'started_on' => '2027-01-04',
            'ends_on' => '2027-03-31',
            'completed_on' => '2027-03-31',
            'status' => 'completed',
        ]);

        $this->postJson('/api/academy/certificates', ['academy_enrollment_id' => $enrollment->id])
            ->assertCreated()
            ->assertJsonPath('data.snapshot.award.student_name', $student->name)
            ->assertJsonPath('data.snapshot.award.student_id', $student->student_number);

        $this->assertDatabaseHas('academy_certificates', [
            'academy_enrollment_id' => $enrollment->id,
            'applicant_id' => null,
            'class_id' => $this->class->id,
        ]);
    }

    public function test_snapshot_survives_template_change_and_revocation_keeps_number(): void
    {
        $first = $this->enrollment();
        $certificate = $this->postJson('/api/academy/certificates', ['academy_enrollment_id' => $first->id])->json('data');
        $this->patchJson("/api/academy/certificate-templates/{$this->template->id}", [
            'title' => 'Changed title', 'body' => 'Changed copy for {student_name}.', 'signatory_title' => 'Registrar',
        ])->assertOk();

        $this->assertSame('Certificate of Completion', $certificate['snapshot']['template']['title']);
        $second = $this->enrollment();
        $this->postJson('/api/academy/certificates', ['academy_enrollment_id' => $second->id])
            ->assertCreated()->assertJsonPath('data.snapshot.template.title', 'Changed title');

        $this->patchJson("/api/academy/certificates/{$certificate['id']}/revoke", ['reason' => 'Wrong student'])
            ->assertOk()->assertJsonPath('data.status', 'revoked')->assertJsonPath('data.revocation_reason', 'Wrong student');
        $this->assertSame($certificate['certificate_number'], AcademyCertificate::find($certificate['id'])->certificate_number);
        $this->patchJson("/api/academy/certificates/{$certificate['id']}/revoke", ['reason' => 'Again'])->assertUnprocessable();
    }

    public function test_unknown_template_token_is_rejected(): void
    {
        $this->patchJson("/api/academy/certificate-templates/{$this->template->id}", ['body' => 'Hello {unknown_token}'])
            ->assertUnprocessable()->assertJsonValidationErrors('body');
    }

    public function test_bulk_issue_creates_one_per_enrollment_and_skips_duplicates(): void
    {
        $first = $this->enrollment();
        $second = $this->enrollment();
        $this->postJson('/api/academy/certificates', ['academy_enrollment_id' => $first->id])->assertCreated();

        $this->postJson('/api/academy/certificates', [
            'class_id' => $this->class->id, 'enrollment_ids' => [$first->id, $second->id],
        ])->assertCreated()->assertJsonPath('data.created', 1)->assertJsonPath('data.skipped', 1);
        $this->assertDatabaseCount('academy_certificates', 2);
    }

    public function test_permissions_gate_reads_issues_and_revocation(): void
    {
        $enrollment = $this->enrollment();
        $certificate = $this->postJson('/api/academy/certificates', ['academy_enrollment_id' => $enrollment->id])->json('data');

        $reader = User::factory()->create();
        $view = Permission::firstOrCreate(['name' => 'view_academy_contracts'], ['category' => 'customer_contracts', 'guard_name' => 'api']);
        $role = Role::firstOrCreate(['name' => 'academy_reader']);
        $role->permissions()->sync([$view->id]);
        $reader->roles()->sync([$role->id]);
        Sanctum::actingAs($reader, ['access']);

        $this->getJson('/api/academy/certificates')->assertOk();
        $this->postJson('/api/academy/certificates', ['academy_enrollment_id' => $enrollment->id])->assertForbidden();
        $this->patchJson("/api/academy/certificates/{$certificate['id']}/revoke", ['reason' => 'x'])->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['roles' => []]), ['access']);
        $this->getJson('/api/academy/certificates')->assertForbidden();
    }
}
