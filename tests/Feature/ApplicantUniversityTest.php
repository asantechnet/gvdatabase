<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Applicant;
use App\Models\Role;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApplicantUniversityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'super_admin']));
        Sanctum::actingAs($user, ['access']);
    }

    private function makeCase(): array
    {
        return $this->postJson('/api/applicants', ['name' => 'University Case'])->assertCreated()->json('data');
    }

    private function makeUniversity(string $name = 'Istanbul University'): array
    {
        $country = Country::firstOrCreate(['code' => 'TUR'], ['name' => 'Turkey', 'is_active' => true]);

        return University::create(['name' => $name, 'country_id' => $country->id, 'is_active' => true])->toArray();
    }

    public function test_applications_track_outcomes_per_university(): void
    {
        $a = $this->makeCase();
        $u1 = $this->makeUniversity();
        $u2 = $this->makeUniversity('Ankara University');

        $this->postJson("/api/applicants/{$a['id']}/universities", ['university_id' => $u1['id'], 'intake' => 'Fall 2026'])->assertCreated()->assertJsonPath('data.status', 'pending');
        $this->postJson("/api/applicants/{$a['id']}/universities", ['university_id' => $u2['id'], 'status' => 'applied'])->assertCreated();

        // Same university + intake twice is a duplicate; a new intake is a re-application.
        $this->postJson("/api/applicants/{$a['id']}/universities", ['university_id' => $u1['id'], 'intake' => 'Fall 2026'])->assertUnprocessable();
        $this->postJson("/api/applicants/{$a['id']}/universities", ['university_id' => $u1['id'], 'intake' => 'Spring 2027'])->assertCreated();
        $this->postJson("/api/applicants/{$a['id']}/universities", ['university_id' => $u1['id'], 'status' => 'enrolled'])->assertUnprocessable();

        $list = $this->getJson("/api/applicants/{$a['id']}/universities")->assertOk()->json('data');
        $this->assertCount(3, $list);

        $first = collect($list)->firstWhere('university_id', $u1['id']);
        $this->putJson("/api/applicants/{$a['id']}/universities/{$first['id']}", ['status' => 'admitted'])->assertOk()->assertJsonPath('data.status', 'admitted');

        $case = $this->getJson("/api/applicants/{$a['id']}")->assertOk()->json('data');
        $this->assertNotEmpty($case['universities']);
    }

    public function test_applications_are_scoped_to_their_case(): void
    {
        $a = $this->makeCase();
        $b = $this->makeCase();
        $u = $this->makeUniversity();
        $app = $this->postJson("/api/applicants/{$a['id']}/universities", ['university_id' => $u['id']])->assertCreated()->json('data');

        // Application of case A is unreachable through case B.
        $this->putJson("/api/applicants/{$b['id']}/universities/{$app['id']}", ['status' => 'admitted'])->assertNotFound();
        $this->deleteJson("/api/applicants/{$b['id']}/universities/{$app['id']}")->assertNotFound();

        $this->deleteJson("/api/applicants/{$a['id']}/universities/{$app['id']}")->assertNoContent();
        $this->assertDatabaseMissing('applicant_universities', ['id' => $app['id'], 'deleted_at' => null]);
    }

    public function test_outcomes_report_counts_admissions_per_university(): void
    {
        $a = $this->makeCase();
        $u = $this->makeUniversity();
        $app = $this->postJson("/api/applicants/{$a['id']}/universities", ['university_id' => $u['id']])->assertCreated()->json('data');
        $this->putJson("/api/applicants/{$a['id']}/universities/{$app['id']}", ['status' => 'admitted'])->assertOk();

        $report = $this->getJson('/api/educational-reports/visa-outcomes')->assertOk()->json('data');
        $row = collect($report['by_university']['rows'])->firstWhere('university', 'Istanbul University');
        $this->assertSame(1, $row['applications']);
        $this->assertSame(1, $row['admitted']);
        $this->assertSame(100.0, (float) $row['admission_rate_pct']);
    }

    public function test_application_endpoints_require_applicant_permissions(): void
    {
        $a = $this->makeCase();
        Sanctum::actingAs(User::factory()->create(), ['access']);

        $this->getJson("/api/applicants/{$a['id']}/universities")->assertForbidden();
        $this->postJson("/api/applicants/{$a['id']}/universities", ['university_id' => 1])->assertForbidden();
    }
}
