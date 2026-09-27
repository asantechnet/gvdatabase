<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrmRelationshipNotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_applicant_relationship_notes_can_be_saved_without_a_full_applicant_update(): void
    {
        $applicant = Applicant::factory()->create(['notes' => null]);
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);

        $response = $this->patchJson('/api/crm/applicant/'.$applicant->id.'/notes', [
            'notes' => 'Prefers communication by email.',
        ])->assertOk();

        $this->assertSame('Prefers communication by email.', $response->json('data.notes'));
        $this->assertSame('Prefers communication by email.', $applicant->fresh()->notes);
    }
}
