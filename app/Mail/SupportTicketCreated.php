<?php

namespace App\Mail;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class SupportTicketCreated extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly SupportTicket $ticket)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('[%s] Support ticket %s — %s', strtoupper($this->ticket->severity), $this->ticket->ticket_number, $this->ticket->subject),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.support-tickets.created',
            with: [
                'severity' => $this->severityDetails(),
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return $this->ticket->attachments->map(fn ($attachment) => Attachment::fromStorageDisk($attachment->disk, $attachment->path)
            ->as($attachment->original_name)
            ->withMime($attachment->mime_type))
            ->all();
    }

    /** @return array{label: string, message: string, attention: string} */
    private function severityDetails(): array
    {
        return match ($this->ticket->severity) {
            'critical' => ['label' => 'Critical', 'message' => 'Potential operational interruption, access issue, or data risk.', 'attention' => 'Immediate attention recommended.'],
            'high' => ['label' => 'High', 'message' => 'The reporter may be blocked from completing important work.', 'attention' => 'Prioritize review as soon as possible.'],
            'low' => ['label' => 'Low', 'message' => 'A minor inconvenience or improvement request.', 'attention' => 'Review when practical.'],
            default => ['label' => 'Normal', 'message' => 'The reporter needs help through the normal support flow.', 'attention' => 'Review in the next support pass.'],
        };
    }
}
