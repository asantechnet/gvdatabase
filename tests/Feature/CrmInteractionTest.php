<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrmInteractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitor_conversations_and_notes_can_be_added_from_relationship_details(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('name', 'super_admin')->firstOrFail());
        Sanctum::actingAs($user, ['access']);
        $visitor = Visitor::create([
            'name' => 'Conversation Test',
            'contact' => '+93700000000',
            'service' => 'Consulting',
            'marketer' => 'Direct',
            'date' => now()->toDateString(),
        ]);

        $this->patchJson("/api/crm/visitor/{$visitor->id}/notes", ['notes' => 'Prefers email follow-up.'])
            ->assertOk()->assertJsonPath('data.notes', 'Prefers email follow-up.');

        $this->postJson("/api/crm/visitor/{$visitor->id}/interactions", [
            'channel' => 'call',
            'title' => 'Initial call',
            'notes' => 'Discussed programme requirements.',
            'contacted_at' => now()->format('Y-m-d H:i'),
        ])->assertCreated()->assertJsonPath('data.title', 'Initial call');

        $this->getJson("/api/visitors/{$visitor->id}")->assertOk()
            ->assertJsonCount(1, 'data.interactions');
    }
}
