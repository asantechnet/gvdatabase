<?php

namespace Tests\Feature;

use App\Http\Controllers\CrmInteractionController;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class CrmRelationshipNotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_relationship_notes_can_be_saved_without_a_full_customer_update(): void
    {
        $customer = Customer::factory()->create(['notes' => null]);
        $request = Request::create('/api/crm/customer/'.$customer->id.'/notes', 'PATCH', [
            'notes' => 'Prefers communication by email.',
        ]);

        $response = app(CrmInteractionController::class)->updateNotes($request, 'customer', $customer->id);

        $this->assertSame('Prefers communication by email.', $response->getData(true)['data']['notes']);
        $this->assertSame('Prefers communication by email.', $customer->fresh()->notes);
    }
}
