<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\SupportTicket */
class SupportTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_number' => $this->ticket_number,
            'category' => $this->category,
            'category_label' => $this->categoryLabel(),
            'severity' => $this->severity,
            'subject' => $this->subject,
            'description' => $this->description,
            'page_url' => $this->page_url,
            'status' => $this->status,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($attachment) => [
                'id' => $attachment->id,
                'name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size' => $attachment->size,
                'download_url' => "/api/support-tickets/{$this->id}/attachments/{$attachment->id}/download",
            ])->values()),
        ];
    }
}
