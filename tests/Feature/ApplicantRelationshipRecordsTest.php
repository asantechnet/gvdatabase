<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Country;
use App\Models\CrmInteraction;
use App\Models\Role;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApplicantRelationshipRecordsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(string $role): User
    {
        $user = User::factory()->create(['acc_status' => 'active']);
        $user->roles()->attach(Role::query()->where('name', $role)->firstOrFail());
        Sanctum::actingAs($user, ['access']);

        return $user;
    }

    public function test_case_records_include_only_the_selected_country_and_attached_universities(): void
    {
        $user = $this->actingAsRole('super_admin');
        $country = Country::forceCreate(['name' => 'Case country', 'code' => 'CC', 'is_active' => true]);
        $university = University::forceCreate(['name' => 'Case university', 'country_id' => $country->id, 'is_active' => true]);
        $otherUniversity = University::forceCreate(['name' => 'Unrelated university', 'country_id' => $country->id, 'is_active' => true]);
        $applicant = Applicant::factory()->create(['service' => 'scholarship']);
        $applicant->scholarshipDetail()->forceCreate(['country_id' => $country->id, 'university_id' => $university->id]);
        $applicant->universities()->create(['university_id' => $university->id]);
        $otherApplicant = Applicant::factory()->create(['service' => 'scholarship']);
        $otherApplicant->scholarshipDetail()->forceCreate(['country_id' => $country->id, 'university_id' => $university->id]);

        CrmInteraction::create([
            'contactable_type' => $country->getMorphClass(), 'contactable_id' => $country->id,
            'channel' => 'note', 'title' => 'Global country note', 'contacted_at' => now(), 'created_by' => $user->id,
        ]);
        $caseCountryRecord = CrmInteraction::create([
            'contactable_type' => $country->getMorphClass(), 'contactable_id' => $country->id,
            'applicant_id' => $applicant->id, 'channel' => 'call', 'title' => 'Case country call',
            'contacted_at' => now(), 'created_by' => $user->id,
        ]);
        CrmInteraction::create([
            'contactable_type' => $university->getMorphClass(), 'contactable_id' => $university->id,
            'applicant_id' => $applicant->id, 'channel' => 'email', 'title' => 'Case university email',
            'contacted_at' => now(), 'created_by' => $user->id,
        ]);
        CrmInteraction::create([
            'contactable_type' => $country->getMorphClass(), 'contactable_id' => $country->id,
            'applicant_id' => $otherApplicant->id, 'channel' => 'call', 'title' => 'Other case call',
            'contacted_at' => now(), 'created_by' => $user->id,
        ]);

        $records = $this->getJson('/api/applicants/'.$applicant->id.'/relationships')
            ->assertOk()->assertJsonCount(2, 'data.records')->json('data.records');
        $this->assertSame(['country', 'university'], collect($records)->pluck('target_type')->sort()->values()->all());
        $this->assertNotContains('Global country note', collect($records)->pluck('title')->all());

        $this->getJson('/api/crm-relationships/country/'.$country->id.'?applicant_id='.$applicant->id)
            ->assertOk()
            ->assertJsonPath('data.notes', null)
            ->assertJsonCount(1, 'data.interactions')
            ->assertJsonPath('data.interactions.0.id', $caseCountryRecord->id);

        $this->postJson('/api/crm-relationships/university/'.$otherUniversity->id.'/interactions', [
            'applicant_id' => $applicant->id, 'channel' => 'note', 'title' => 'Unlinked',
        ])->assertStatus(422)->assertJsonPath('code', 'business.relationship_not_linked_to_case');

        $this->postJson('/api/crm-relationships/university/'.$university->id.'/interactions', [
            'applicant_id' => $applicant->id, 'channel' => 'note', 'title' => 'Linked',
        ])->assertCreated();
        $this->assertDatabaseHas('crm_interactions', [
            'applicant_id' => $applicant->id, 'contactable_type' => $university->getMorphClass(), 'title' => 'Linked',
        ]);

        $this->patchJson('/api/crm-relationships/country/'.$country->id.'/notes?applicant_id='.$applicant->id, [
            'notes' => 'Must not change',
        ])->assertStatus(422)->assertJsonPath('code', 'business.relationship_not_linked_to_case');
    }

    public function test_case_relationship_reads_require_case_scope(): void
    {
        $applicant = Applicant::factory()->create(['service' => 'scholarship']);
        $viewer = User::factory()->create(['acc_status' => 'active']);
        $viewer->roles()->attach(Role::query()->where('name', 'viewer')->firstOrFail());
        Sanctum::actingAs($viewer, ['access']);

        $this->getJson('/api/applicants/'.$applicant->id.'/relationships')->assertForbidden();
    }
}
