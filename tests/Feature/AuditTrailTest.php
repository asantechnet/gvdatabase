<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\AuditLog;
use App\Models\AuditResourceSetting;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\AuditRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Auditing is opt-in. These tests exercise the audit surface, so
        // explicitly enable every resource first.
        AuditRegistry::ensureSettings();
        AuditResourceSetting::query()->update(['enabled' => true]);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create(['acc_status' => 'active']);
        $user->roles()->attach(Role::query()->firstOrCreate(['name' => 'super_admin']));

        return $user;
    }

    private function userWithPermissions(string ...$permissions): User
    {
        $user = User::factory()->create(['acc_status' => 'active']);
        $role = Role::query()->firstOrCreate(['name' => 'audit-trail-test-'.Str::random(8)]);

        foreach ($permissions as $name) {
            $permission = Permission::query()->firstOrCreate(
                ['name' => $name],
                ['category' => 'audit', 'guard_name' => 'web'],
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $user->roles()->attach($role);

        return $user;
    }

    private function trail(string $resource, array $ids)
    {
        return $this->getJson('/api/audit-logs/row-trails?'.http_build_query([
            'resource' => $resource,
            'ids' => $ids,
        ]));
    }

    public function test_returns_the_latest_audit_entry_per_record(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);
        $updated = Applicant::factory()->create(['name' => 'Rahimi Trading', 'contact' => '0700000000']);
        $updated->update(['contact' => '0799999999']);
        $updated->update(['name' => 'Rahimi International']);
        $created = Applicant::factory()->create(['name' => 'Safi Company']);
        $latestId = AuditLog::query()
            ->where('subject_type', 'applicants')
            ->where('auditable_id', $updated->id)
            ->max('id');

        $response = $this->trail('applicants', [$updated->id, $created->id])
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertSame($updated->id, $response->json('data.0.id'));
        $this->assertSame($latestId, $response->json('data.0.audit_id'));
        $this->assertSame('updated', $response->json('data.0.event'));
        $this->assertSame('name', $response->json('data.0.changed_fields.0.field'));
        $this->assertSame('Rahimi Trading', $response->json('data.0.changed_fields.0.old_label'));

        $this->assertSame($created->id, $response->json('data.1.id'));
        $this->assertSame('created', $response->json('data.1.event'));
        $this->assertSame([], $response->json('data.1.changed_fields'));
    }

    public function test_deleted_records_keep_their_deletion_entry(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);
        $applicant = Applicant::factory()->create(['name' => 'Archived Company']);
        $applicant->delete();

        $this->trail('applicants', [$applicant->id])
            ->assertOk()
            ->assertJsonPath('data.0.event', 'deleted')
            ->assertJsonPath('data.0.id', $applicant->id);
    }

    public function test_audit_read_permission_is_required(): void
    {
        Sanctum::actingAs(User::factory()->create(['acc_status' => 'active']), ['access']);

        $this->trail('applicants', [1])->assertForbidden();
    }

    public function test_validation_rejects_unknown_resource_duplicates_excess_and_non_integer_ids(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);

        $this->trail('not-a-resource', [1])->assertUnprocessable();
        $this->trail('applicants', [1, 1])->assertUnprocessable();
        $this->trail('applicants', ['abc'])->assertUnprocessable();
        $this->trail('applicants', range(1, 101))->assertUnprocessable();
        $this->trail('applicants', [])->assertUnprocessable();
    }

    public function test_existing_history_survives_disabling_a_resource_and_new_rows_stay_neutral(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);
        $applicant = Applicant::factory()->create(['name' => 'Before Disable']);
        $setting = AuditResourceSetting::query()->where('resource_key', 'applicants')->firstOrFail();
        $setting->update(['enabled' => false]);
        $applicant->update(['name' => 'After Disable']);
        $neutral = Applicant::factory()->create(['name' => 'Never Audited']);

        $this->trail('applicants', [$applicant->id])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.event', 'created');
        $this->trail('applicants', [$neutral->id])->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_a_page_of_rows_is_enriched_with_a_bounded_query_count(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);
        $applicants = Applicant::factory()->count(25)->create();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->trail('applicants', $applicants->modelKeys())->assertOk()->assertJsonCount(25, 'data');
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(8, $queryCount, "Row trails used {$queryCount} queries.");
    }

    public function test_team_reviewer_receives_only_visible_employee_trails(): void
    {
        $viewer = $this->userWithPermissions('view_audit_logs', 'view_team_audit_logs');
        Employee::factory()->create(['user_id' => $viewer->id, 'name' => 'Visible Reviewer']);

        Sanctum::actingAs($viewer, ['access']);
        $own = Applicant::factory()->create(['name' => 'Visible Applicant']);

        $other = User::factory()->create(['acc_status' => 'active']);
        Employee::factory()->create(['user_id' => $other->id, 'name' => 'Hidden Reviewer']);

        Sanctum::actingAs($other, ['access']);
        $hidden = Applicant::factory()->create(['name' => 'Hidden Applicant']);

        Sanctum::actingAs($viewer, ['access']);
        $this->trail('applicants', [$own->id, $hidden->id])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);
    }

    public function test_audit_coloring_setting_is_optional_and_gates_public_branding(): void
    {
        $setting = Setting::create([
            'website_title' => 'Audit Test Firm',
            'short_company_name' => 'Audit Test',
            'original_company_name' => 'Audit Test Firm',
            'phone' => '0700000000',
            'physical_address' => 'Kabul',
            'audit_coloring_enabled' => false,
        ]);

        $this->assertFalse($setting->fresh()->audit_coloring_enabled);

        Sanctum::actingAs($this->superAdmin(), ['access']);
        $this->patchJson("/api/settings/{$setting->id}", ['audit_coloring_enabled' => true])
            ->assertOk()
            ->assertJsonPath('data.audit_coloring_enabled', true);

        $this->getJson('/api/company-branding')
            ->assertOk()
            ->assertJsonPath('data.audit_coloring_enabled', true);
    }
}
