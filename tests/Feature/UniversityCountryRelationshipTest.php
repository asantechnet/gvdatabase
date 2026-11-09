<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\CrmInteraction;
use App\Models\Role;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UniversityCountryRelationshipTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(string $role): User
    {
        $user = User::factory()->create(['acc_status' => 'active']);
        $user->roles()->attach(Role::query()->where('name', $role)->firstOrFail());
        Sanctum::actingAs($user, ['access']);

        return $user;
    }

    public function test_global_relationship_reads_and_writes_notes_and_interactions(): void
    {
        $user = $this->actingAsRole('super_admin');
        $country = Country::forceCreate(['name' => 'Relationship country', 'code' => 'RC', 'is_active' => true]);
        $interaction = CrmInteraction::create([
            'contactable_type' => $country->getMorphClass(),
            'contactable_id' => $country->id,
            'channel' => 'note',
            'title' => 'Embassy contact',
            'notes' => 'Called the embassy',
            'contacted_at' => now(),
            'created_by' => $user->id,
        ]);

        $this->getJson('/api/crm-relationships/country/'.$country->id)
            ->assertOk()
            ->assertJsonPath('data.type', 'country')
            ->assertJsonPath('data.notes', null)
            ->assertJsonPath('data.interactions.0.id', $interaction->id);

        $this->patchJson('/api/crm-relationships/country/'.$country->id.'/notes', ['notes' => 'Global note'])
            ->assertOk()->assertJsonPath('data.notes', 'Global note');
        $this->assertDatabaseHas('countries', ['id' => $country->id, 'notes' => 'Global note']);

        $this->getJson('/api/crm-relationships/visitor/1')->assertNotFound();
    }

    public function test_global_relationship_writes_require_master_data_edit_permission(): void
    {
        $country = Country::forceCreate(['name' => 'Protected country', 'code' => 'PC', 'is_active' => true]);
        Sanctum::actingAs(User::factory()->create(['acc_status' => 'active']), ['access']);

        $this->getJson('/api/crm-relationships/country/'.$country->id)->assertForbidden();
        $this->patchJson('/api/crm-relationships/country/'.$country->id.'/notes', ['notes' => 'Nope'])->assertForbidden();
    }

    public function test_notes_and_applicant_scope_columns_are_additive_and_idempotent(): void
    {
        $this->assertTrue(Schema::hasColumn('countries', 'notes'));
        $this->assertTrue(Schema::hasColumn('universities', 'notes'));
        $this->assertTrue(Schema::hasColumn('crm_interactions', 'applicant_id'));

        $notesMigration = require database_path('migrations/2026_11_07_000010_add_notes_to_countries_and_universities.php');
        $scopeMigration = require database_path('migrations/2026_11_07_000011_add_applicant_scope_to_crm_interactions.php');
        $notesMigration->up();
        $notesMigration->up();
        $scopeMigration->up();
        $scopeMigration->up();

        $this->assertTrue(Schema::hasColumn('universities', 'notes'));
        $this->assertTrue(Schema::hasColumn('crm_interactions', 'applicant_id'));
    }
}
