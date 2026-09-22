<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrmInteractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_conversations_and_notes_can_be_added_from_relationship_details(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('name', 'super_admin')->firstOrFail());
        Sanctum::actingAs($user, ['access']);
        $lead = Lead::create(['name' => 'Conversation Test', 'company' => 'Example', 'contact' => '+93700000000']);

        $this->patchJson("/api/crm/lead/{$lead->id}/notes", ['notes' => 'Prefers email follow-up.'])
            ->assertOk()->assertJsonPath('data.notes', 'Prefers email follow-up.');

        $this->postJson("/api/crm/lead/{$lead->id}/interactions", [
            'channel' => 'call',
            'title' => 'Initial call',
            'notes' => 'Discussed programme requirements.',
            'contacted_at' => now()->format('Y-m-d H:i'),
        ])->assertCreated()->assertJsonPath('data.title', 'Initial call');

        $this->getJson("/api/leads/{$lead->id}")->assertOk()
            ->assertJsonCount(1, 'data.interactions');
    }
}
