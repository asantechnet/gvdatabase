<?php

namespace Tests\Feature;

use App\Http\Controllers\LeadController;
use App\Http\Controllers\VisitorController;
use App\Models\CrmInteraction;
use App\Models\Applicant;
use App\Models\Lead;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class CrmRelationshipTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_notes_and_conversations_follow_visitor_through_lead_to_applicant(): void
    {
        $user = User::factory()->create();
        $visitor = $this->visitor();
        $interaction = $this->interaction($visitor, $user);
        $request = Request::create('/api/leads/from-visitor/'.$visitor->id, 'POST');
        $request->setUserResolver(fn () => $user);

        app(LeadController::class)->fromVisitor($request, $visitor);
        $lead = Lead::where('visitor_id', $visitor->id)->firstOrFail();

        $this->assertSame('Prefers morning calls.', $lead->notes);
        $this->assertSame('crm@example.test', $lead->email);
        $this->assertSame($lead->getMorphClass(), $interaction->fresh()->contactable_type);
        $this->assertSame($lead->id, $interaction->fresh()->contactable_id);

        app(LeadController::class)->convert($lead);
        $applicant = Applicant::where('contact', '+93700000000')->firstOrFail();

        $this->assertSame('Prefers morning calls.', $applicant->notes);
        $this->assertSame('crm@example.test', $applicant->email);
        $this->assertSame($applicant->getMorphClass(), $interaction->fresh()->contactable_type);
        $this->assertSame($applicant->id, $interaction->fresh()->contactable_id);
        $this->assertSame('Discovery call', $applicant->interactions()->firstOrFail()->title);
    }

    public function test_direct_visitor_conversion_also_preserves_relationship_data(): void
    {
        $user = User::factory()->create();
        $visitor = $this->visitor();
        $interaction = $this->interaction($visitor, $user);

        app(VisitorController::class)->convertToApplicant($visitor);
        $applicant = Applicant::where('contact', '+93700000000')->firstOrFail();

        $this->assertSame('Prefers morning calls.', $applicant->notes);
        $this->assertSame('crm@example.test', $applicant->email);
        $this->assertSame($applicant->getMorphClass(), $interaction->fresh()->contactable_type);
        $this->assertSame($applicant->id, $interaction->fresh()->contactable_id);
    }

    private function visitor(): Visitor
    {
        return Visitor::create([
            'name' => 'CRM Transfer Test',
            'company' => 'Example Co',
            'contact' => '+93700000000',
            'email' => 'crm@example.test',
            'service' => 'Consulting',
            'marketer' => 'Internal',
            'notes' => 'Prefers morning calls.',
            'date' => '2026-09-01',
        ]);
    }

    private function interaction(Visitor $visitor, User $user): CrmInteraction
    {
        return $visitor->interactions()->create([
            'created_by' => $user->id,
            'channel' => 'call',
            'title' => 'Discovery call',
            'notes' => 'Discussed requirements and next steps.',
            'contacted_at' => '2026-09-01 09:30:00',
            'follow_up_at' => '2026-09-02 09:30:00',
        ]);
    }
}
