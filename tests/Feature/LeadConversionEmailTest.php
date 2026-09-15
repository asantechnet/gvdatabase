<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeadConversionEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversion_requires_a_valid_email_when_the_lead_does_not_have_one(): void
    {
        $lead = $this->lead(null);
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);

        $this->postJson("/api/leads/{$lead->id}/convert-to-customer")
            ->assertUnprocessable();

        $this->assertDatabaseMissing('customers', ['contact' => $lead->contact]);
        $this->assertNull($lead->fresh()->converted_at);
    }

    public function test_conversion_saves_the_supplied_email_on_both_records_atomically(): void
    {
        $lead = $this->lead('not-an-email');
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);

        $this->postJson("/api/leads/{$lead->id}/convert-to-customer", ['email' => 'owner@example.test'])
            ->assertCreated();

        $this->assertSame('owner@example.test', $lead->fresh()->email);
        $this->assertSame('owner@example.test', Customer::query()->where('contact', $lead->contact)->sole()->email);
    }

    private function lead(?string $email): Lead
    {
        return Lead::create([
            'name' => 'Email-ready prospect', 'company' => 'Example Co', 'contact' => '+93700000004',
            'email' => $email, 'stage' => 'proposal', 'probability' => 75,
        ]);
    }
}
