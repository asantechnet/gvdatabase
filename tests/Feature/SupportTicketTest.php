<?php

namespace Tests\Feature;

use App\Mail\SupportTicketCreated;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authenticated_user_can_submit_a_ticket_that_is_saved_and_emailed(): void
    {
        Mail::fake();
        config()->set('support.ticket_recipient', 'owner@example.test');
        $user = User::factory()->create(['acc_status' => 'active']);
        Sanctum::actingAs($user, ['access']);

        $response = $this->postJson('/api/support-tickets', [
            'category' => 'technical',
            'severity' => 'critical',
            'subject' => 'Invoice form cannot be saved',
            'description' => 'Saving an invoice returns me to the form without creating the invoice record.',
            'page_url' => '/innvoice?applicant=14',
        ])->assertCreated();

        $ticketId = $response->json('data.id');
        $this->assertDatabaseHas('support_tickets', [
            'id' => $ticketId,
            'user_id' => $user->id,
            'ticket_number' => 'SUP-000001',
            'severity' => 'critical',
            'recipient_email' => 'owner@example.test',
        ]);
        Mail::assertSent(SupportTicketCreated::class, function (SupportTicketCreated $mail) use ($ticketId): bool {
            return $mail->ticket->id === $ticketId && $mail->hasTo('owner@example.test');
        });
    }

    public function test_ticket_submission_is_rejected_without_a_configured_support_recipient(): void
    {
        config()->set('support.ticket_recipient', null);
        $user = User::factory()->create(['acc_status' => 'active']);
        Sanctum::actingAs($user, ['access']);

        $this->postJson('/api/support-tickets', [
            'category' => 'account',
            'severity' => 'normal',
            'subject' => 'I need account assistance',
            'description' => 'I need help changing the information attached to my account profile.',
        ])->assertServiceUnavailable()
            ->assertJsonPath('code', 'business.support_recipient_not_configured');

        $this->assertSame(0, SupportTicket::query()->count());
    }

    public function test_a_ticket_can_include_safe_supporting_files(): void
    {
        Mail::fake();
        Storage::fake('local');
        config()->set('support.ticket_recipient', 'owner@example.test');
        $user = User::factory()->create(['acc_status' => 'active']);
        Sanctum::actingAs($user, ['access']);

        $response = $this->post('/api/support-tickets', [
            'category' => 'technical',
            'severity' => 'high',
            'subject' => 'A screenshot shows the save failure',
            'description' => 'Saving an invoice fails after choosing a applicant and entering the required details.',
            'attachments' => [UploadedFile::fake()->create('save-error.txt', 12, 'text/plain')],
        ], ['Accept' => 'application/json'])->assertCreated();

        $ticket = SupportTicket::with('attachments')->findOrFail($response->json('data.id'));
        $this->assertCount(1, $ticket->attachments);
        Storage::disk('local')->assertExists($ticket->attachments->first()->path);
        $this->get($response->json('data.attachments.0.download_url'))->assertOk();
    }

    public function test_ticket_history_is_limited_to_the_authenticated_reporter(): void
    {
        config()->set('support.ticket_recipient', 'owner@example.test');
        $reporter = User::factory()->create(['acc_status' => 'active']);
        $otherUser = User::factory()->create(['acc_status' => 'active']);
        SupportTicket::create([
            'user_id' => $reporter->id,
            'ticket_number' => 'SUP-000101',
            'category' => 'technical',
            'severity' => 'normal',
            'subject' => 'My ticket',
            'description' => 'This is a detailed request with enough information for support to review.',
            'status' => 'open',
            'submitted_at' => now(),
        ]);
        SupportTicket::create([
            'user_id' => $otherUser->id,
            'ticket_number' => 'SUP-000102',
            'category' => 'billing',
            'severity' => 'high',
            'subject' => 'Another user ticket',
            'description' => 'This ticket must not be visible to a different authenticated user.',
            'status' => 'open',
            'submitted_at' => now(),
        ]);
        Sanctum::actingAs($reporter, ['access']);

        $this->getJson('/api/support-tickets')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.ticket_number', 'SUP-000101');
    }
}
