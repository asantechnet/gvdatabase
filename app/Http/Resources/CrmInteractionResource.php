<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CrmInteractionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'channel' => $this->channel, 'title' => $this->title, 'notes' => $this->notes,
            'contacted_at' => $this->contacted_at?->toISOString(), 'follow_up_at' => $this->follow_up_at?->toISOString(),
            'created_by' => $this->created_by, 'creator' => $this->whenLoaded('creator', fn () => $this->creator?->only(['id', 'name']))];
    }
}
