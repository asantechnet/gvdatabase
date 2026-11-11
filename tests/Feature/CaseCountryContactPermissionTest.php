<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\CrmInteraction;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CaseCountryContactPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_case_country_contacts_are_opt_in_and_super_admin_has_access(): void
    {
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);

        $admin = User::factory()->create(['roles' => ['super_admin'], 'acc_status' => 'active']);
        Sanctum::actingAs($admin, ['access']);
        $country = Country::forceCreate([
            'name' => 'Contact country',
            'code' => 'CC',
            'is_active' => true,
            'phone' => '+93700000000',
            'email' => 'embassy@example.test',
        ]);
        $applicant = $this->postJson('/api/applicants', [
            'name' => 'Country contact case',
            'country_id' => $country->id,
        ])->assertCreated()->json('data');

        $interaction = CrmInteraction::create([
            'contactable_type' => $country->getMorphClass(),
            'contactable_id' => $country->id,
            'applicant_id' => $applicant['id'],
            'channel' => 'note',
            'title' => 'Embassy call',
            'contacted_at' => now(),
            'created_by' => $admin->id,
        ]);

        $this->getJson("/api/crm-relationships/country/{$country->id}?applicant_id={$applicant['id']}")
            ->assertOk()
            ->assertJsonPath('data.interactions.0.id', $interaction->id);
        $this->getJson("/api/applicants/{$applicant['id']}/relationships")
            ->assertOk()
            ->assertJsonPath('data.country.id', $country->id)
            ->assertJsonCount(1, 'data.records');

        [$reader, $readerRole] = $this->caseReader();
        Sanctum::actingAs($reader, ['access']);

        $this->getJson('/api/crm-relationships/country/'.$country->id)->assertOk();
        $this->getJson("/api/crm-relationships/country/{$country->id}?applicant_id={$applicant['id']}")
            ->assertForbidden();
        $this->getJson("/api/applicants/{$applicant['id']}/relationships")
            ->assertOk()
            ->assertJsonPath('data.country', null)
            ->assertJsonCount(0, 'data.records');

        $readerRole->permissions()->attach(
            Permission::query()->where('name', 'view_case_country_contacts')->firstOrFail(),
        );

        $this->getJson("/api/crm-relationships/country/{$country->id}?applicant_id={$applicant['id']}")
            ->assertOk();
        $this->getJson("/api/applicants/{$applicant['id']}/relationships")
            ->assertOk()
            ->assertJsonPath('data.country.id', $country->id)
            ->assertJsonCount(1, 'data.records');
    }

    public function test_default_role_grants_do_not_include_case_country_contacts(): void
    {
        $grants = RolePermissionSeeder::grants();

        $this->assertNotContains('view_case_country_contacts', $grants['admin']);
        $this->assertNotContains('view_case_country_contacts', $grants['manager']);
        $this->assertNotContains('view_case_country_contacts', $grants['staff']);
        $this->assertContains('view_case_country_contacts', PermissionSeeder::catalogNames());
    }

    /** @return array{0: User, 1: Role} */
    private function caseReader(): array
    {
        $role = Role::create(['name' => 'case_reader_'.uniqid()]);
        $role->permissions()->attach(
            Permission::query()->whereIn('name', [
                'view_applicants',
                'view_all_applicants',
                'view_country',
            ])->pluck('id'),
        );
        $user = User::factory()->create(['acc_status' => 'active']);
        $user->roles()->attach($role);

        return [$user, $role];
    }
}
