<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Country;
use App\Models\Currency;
use App\Models\EducationLevel;
use App\Models\Language;
use App\Models\Subject;
use App\Models\User;
use App\Services\Contracts\BuiltInContractTemplateService;
use App\Support\BuiltInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Staff-issued portal logins: a chosen email and password on the visa case,
 * and the credentials a built-in contract issues when it creates the applicant.
 */
class ApplicantPortalAccountTest extends TestCase
{
    use RefreshDatabase;

    private Currency $currency;

    private Language $language;

    private Country $country;

    private EducationLevel $education;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
        Currency::factory()->create(['code' => 'USD', 'rate' => 1, 'is_base' => true]);
        $this->currency = Currency::factory()->create(['code' => 'AFN', 'rate' => 1, 'is_base' => false]);
        $this->language = Language::create(['name' => 'English', 'code' => 'en']);
        $this->country = Country::create(['name' => 'Default destination', 'code' => 'QA']);
        $this->education = EducationLevel::create(['name' => 'Bachelor', 'is_active' => true]);
    }

    public function test_staff_can_create_a_verified_applicant_account_with_a_chosen_password(): void
    {
        $case = Applicant::factory()->create();

        $response = $this->postJson("/api/applicants/{$case->id}/portal-invitation", [
            'name' => 'Amina Student',
            'email' => 'Amina@Example.test',
            'password' => 'Portal#Secret1',
        ])->assertCreated()->assertJsonPath('data.verified', true);

        $user = User::query()->findOrFail($response->json('data.user_id'));
        $this->assertSame('amina@example.test', $user->email);
        $this->assertTrue(Hash::check('Portal#Secret1', $user->password));
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertFalse($user->must_set_password);
        $this->assertSame(['applicant'], $user->roleNames());
        $this->assertSame($user->id, $case->fresh()->user_id);
    }

    public function test_an_invitation_without_a_password_still_requires_email_verification(): void
    {
        $case = Applicant::factory()->create();

        $response = $this->postJson("/api/applicants/{$case->id}/portal-invitation", [
            'name' => 'Amina Student',
            'email' => 'amina@example.test',
        ])->assertCreated()->assertJsonPath('data.verified', false);

        $user = User::query()->findOrFail($response->json('data.user_id'));
        $this->assertNull($user->email_verified_at);
        $this->assertTrue($user->must_set_password);
    }

    public function test_the_suggested_login_uses_the_applicant_email_or_a_readable_unique_name(): void
    {
        $ownEmail = $this->postJson('/api/applicants/portal-credentials', [
            'service' => BuiltInService::SCHOLARSHIP,
            'name' => 'Ahmad Wali',
            'email' => 'ahmad@example.test',
            'education_level_id' => $this->education->id,
        ])->assertOk()->json('data');

        $this->assertSame('ahmad@example.test', $ownEmail['email']);
        $this->assertGreaterThanOrEqual(12, strlen($ownEmail['password']));

        $generated = $this->postJson('/api/applicants/portal-credentials', [
            'service' => BuiltInService::SCHOLARSHIP,
            'name' => 'Ahmad Wali',
            'education_level_id' => $this->education->id,
        ])->assertOk()->json('data');

        $this->assertMatchesRegularExpression('/^ahmad-wali\.bachelor\.\d+@example\.com$/', $generated['email']);
        $this->assertNotSame($ownEmail['password'], $generated['password']);

        // An address already owned by another account falls back to a generated one.
        User::factory()->create(['email' => 'taken@example.test']);
        $fallback = $this->postJson('/api/applicants/portal-credentials', [
            'service' => BuiltInService::SCHOLARSHIP,
            'name' => 'Ahmad Wali',
            'email' => 'taken@example.test',
            'education_level_id' => $this->education->id,
        ])->assertOk()->json('data');

        $this->assertStringStartsWith('ahmad-wali.bachelor.', $fallback['email']);
    }

    public function test_creating_a_scholarship_contract_issues_the_account_with_the_shown_credentials(): void
    {
        $payload = $this->scholarshipPayload();

        $contract = $this->postJson('/api/contracts/scholarship', [
            ...$payload,
            'create_portal_user' => true,
            'portal_user_email' => 'sara.portal@example.test',
            'portal_user_password' => 'Portal#Secret1',
        ])->assertCreated()->json('data');

        $applicant = Applicant::query()->findOrFail($contract['applicant_id']);
        $user = User::query()->findOrFail($applicant->user_id);
        $this->assertSame('sara.portal@example.test', $user->email);
        $this->assertSame('Sara Ahmadi', $user->name);
        $this->assertTrue(Hash::check('Portal#Secret1', $user->password));
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertFalse($user->must_set_password);
        $this->assertSame(['applicant'], $user->roleNames());
    }

    public function test_a_contract_without_the_option_creates_no_portal_account(): void
    {
        $contract = $this->postJson('/api/contracts/scholarship', $this->scholarshipPayload())
            ->assertCreated()->json('data');

        $this->assertNull(Applicant::query()->findOrFail($contract['applicant_id'])->user_id);
    }

    public function test_deleting_the_portal_user_unlinks_the_case_and_revokes_its_sessions(): void
    {
        $case = Applicant::factory()->create();
        $created = $this->postJson("/api/applicants/{$case->id}/portal-invitation", [
            'name' => 'Amina Student',
            'email' => 'amina@example.test',
            'password' => 'Portal#Secret1',
        ])->assertCreated()->json('data.user_id');
        $user = User::query()->findOrFail($created);
        $user->createToken('existing session', ['access']);

        $this->deleteJson("/api/applicants/{$case->id}/portal-user")->assertOk();

        $this->assertNull($case->fresh()->user_id);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->deleteJson("/api/applicants/{$case->id}/portal-user")->assertUnprocessable();
        // The case accepts a fresh account afterwards.
        $this->postJson("/api/applicants/{$case->id}/portal-invitation", [
            'name' => 'Amina Student',
            'email' => 'amina.new@example.test',
            'password' => 'Portal#Secret2',
        ])->assertCreated();
    }

    /** A valid scholarship contract: the strict field set needs its own references. */
    private function scholarshipPayload(array $extra = []): array
    {
        $template = app(BuiltInContractTemplateService::class)->ensure(BuiltInService::SCHOLARSHIP);
        $version = $template->versions()->create([
            'language_id' => $this->language->id, 'country_id' => $this->country->id,
            'version_number' => 1, 'name' => 'Agreed terms', 'status' => 'active',
            'is_current' => true, 'current_scope_key' => implode(':', [$template->id, $this->country->id, 'null', $this->language->id]),
            'content' => '<p>{{contract.number}} {{customer.name}} {{service.fee}}</p>',
        ]);

        return [...[
            'name' => 'Sara Ahmadi', 'contact' => '0701234567',
            'father_name' => 'Father', 'father_phone' => '0701111111',
            'grade_score' => 80, 'country_id' => $this->country->id,
            'education_level_id' => $this->education->id,
            'favorite_subject_ids' => [Subject::firstOrCreate(['name' => 'Mathematics'], ['is_active' => true])->id],
            'application_fee' => 120, 'fee' => 120, 'discount' => 0,
            'currency_id' => $this->currency->id, 'currency_rate' => 1,
            'language_id' => $this->language->id,
            'contract_template_version_id' => $version->id,
        ], ...$extra];
    }
}
