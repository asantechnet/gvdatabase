<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrmRelationshipNotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_relationship_notes_can_be_saved_without_a_full_customer_update(): void
    {
        $customer = Customer::factory()->create(['notes' => null]);
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);

        $response = $this->patchJson('/api/crm/customer/'.$customer->id.'/notes', [
            'notes' => 'Prefers communication by email.',
        ])->assertOk();

        $this->assertSame('Prefers communication by email.', $response->json('data.notes'));
        $this->assertSame('Prefers communication by email.', $customer->fresh()->notes);
    }
}
