<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Currency;
use App\Models\User;
use App\Support\BuiltInService;
use Database\Seeders\ApplicantPortalDemoSeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ApplicantPortalDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_demo_seeding_uses_current_schema_and_restores_the_same_identity(): void
    {
        $this->seed(ApplicantPortalDemoSeeder::class);
        $user = User::where('email', 'portal.applicant@example.test')->sole();
        $applicant = $user->applicantCase;
        $detail = $applicant->scholarshipDetail;

        $this->assertSame(BuiltInService::SCHOLARSHIP, $applicant->service);
        $this->assertNotNull($detail);
        $this->assertFalse(Schema::hasColumn('applicants', 'application_date'));
        $this->assertArrayNotHasKey('application_date', $applicant->getAttributes());
        $detail->update(['passport_id' => 'PRESERVED-DEMO']);
        $detail->delete();
        $applicant->delete();
        $user->delete();

        $this->seed(ApplicantPortalDemoSeeder::class);
        $this->seed(ApplicantPortalDemoSeeder::class);

        $this->assertSame($user->id, User::where('email', $user->email)->sole()->id);
        $restored = Applicant::where('user_id', $user->id)->sole();
        $this->assertSame($applicant->id, $restored->id);
        $this->assertSame($detail->id, $restored->scholarshipDetail->id);
        $this->assertSame('PRESERVED-DEMO', $restored->scholarshipDetail->passport_id);
        $this->assertTrue($user->fresh()->roles->contains('name', 'applicant'));
        $this->assertSame(1, $restored->scholarshipDetail()->withTrashed()->count());
    }

    public function test_complete_demo_seed_chain_runs_against_the_simplified_schema(): void
    {
        $this->seed(DatabaseSeeder::class);

        $applicant = Applicant::where('email', 'portal.applicant@example.test')->sole();
        $this->assertNotNull($applicant->scholarshipDetail);
        $this->assertNotNull($applicant->user);
        $this->assertSame(BuiltInService::SCHOLARSHIP, $applicant->service);
        foreach (['invoices', 'companies', 'services', 'packages', 'leads'] as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
    }

    public function test_currency_defaults_preserve_existing_base_rates_and_archived_records(): void
    {
        $base = Currency::factory()->create(['code' => 'USD', 'is_base' => true]);
        $foreign = Currency::factory()->create(['code' => 'AFN', 'is_base' => false, 'rate' => 72]);
        $archived = Currency::factory()->create(['code' => 'EUR', 'is_base' => false, 'rate' => 0.9]);
        $archived->delete();
        $this->seed(CurrencySeeder::class);
        $count = Currency::withTrashed()->count();
        $this->seed(CurrencySeeder::class);

        $this->assertSame($count, Currency::withTrashed()->count());
        $this->assertSame($base->id, Currency::where('is_base', true)->sole()->id);
        $this->assertSame(72.0, (float) $foreign->fresh()->rate);
        $this->assertTrue($archived->fresh()->trashed());
        $this->assertSame(0.9, (float) $archived->fresh()->rate);
    }
}
