<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecurringEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'description' => $this->description,
            'event_type' => $this->event_type, 'status' => $this->status, 'frequency' => $this->frequency,
            'interval' => $this->interval, 'start_date' => $this->start_date->toDateString(),
            'next_occurrence_date' => $this->next_occurrence_date->toDateString(),
            'last_occurrence_date' => $this->last_occurrence_date?->toDateString(), 'end_date' => $this->end_date?->toDateString(),
            'payload' => $this->payload, 'owner' => $this->whenLoaded('owner', fn () => ['id' => $this->owner->id, 'name' => $this->owner->name]),
            'occurrences' => RecurringEventOccurrenceResource::collection($this->whenLoaded('occurrences')),
            'created_at' => $this->created_at, 'updated_at' => $this->updated_at,
        ];
    }
}
