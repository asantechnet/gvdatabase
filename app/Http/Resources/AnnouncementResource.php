<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sender_id' => $this->sender_id,
            'sender' => $this->whenLoaded('sender', fn () => $this->sender ? [
                'id' => $this->sender->id,
                'name' => $this->sender->name,
            ] : null),
            'title' => $this->title,
            'message' => $this->message,
            'labels' => $this->labels ?? [],
            'audience' => $this->audience,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
