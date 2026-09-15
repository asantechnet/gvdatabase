<?php

namespace Tests\Feature;

use App\Http\Controllers\LeadController;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class LeadConfidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_manual_confidence_survives_normal_pipeline_changes_until_restored(): void
    {
        $owner = User::factory()->create();
        $lead = Lead::create([
            'name' => 'Northwind', 'contact' => '+93700000000', 'stage' => 'new',
            'probability' => 10, 'owner_id' => $owner->id,
        ]);
        $controller = app(LeadController::class);

        $controller->update($this->request($owner, ['probability' => 63]), $lead);
        $this->assertSame(63, $lead->fresh()->probability);
        $this->assertTrue($lead->fresh()->probability_is_manual);

        $controller->update($this->request($owner, ['stage' => 'contacted']), $lead->fresh());
        $this->assertSame(63, $lead->fresh()->probability);
        $this->assertTrue($lead->fresh()->probability_is_manual);

        $controller->update($this->request($owner, ['probability_is_manual' => false]), $lead->fresh());
        $this->assertSame(25, $lead->fresh()->probability);
        $this->assertFalse($lead->fresh()->probability_is_manual);
    }

    public function test_terminal_outcomes_keep_their_factual_confidence(): void
    {
        $owner = User::factory()->create();
        $lead = Lead::create([
            'name' => 'Terminal lead', 'contact' => '+93700000001', 'stage' => 'proposal',
            'probability' => 42, 'probability_is_manual' => true, 'owner_id' => $owner->id,
        ]);

        app(LeadController::class)->update($this->request($owner, ['stage' => 'won']), $lead);

        $this->assertSame(100, $lead->fresh()->probability);
        $this->assertFalse($lead->fresh()->probability_is_manual);
    }

    /** @param array<string, mixed> $payload */
    private function request(User $user, array $payload): Request
    {
        $request = Request::create('/api/leads', 'PUT', $payload);
        $request->setUserResolver(fn () => $user);

        return $request;
    }
}
