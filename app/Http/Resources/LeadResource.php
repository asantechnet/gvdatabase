<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'visitor_id' => $this->visitor_id, 'applicant_id' => $this->applicant_id, 'name' => $this->name,
            'company' => $this->company, 'contact' => $this->contact, 'email' => $this->email, 'service_interest' => $this->service_interest,
            'marketer' => $this->marketer, 'marketer_id' => $this->marketer_id,
            'marketer_employee' => $this->whenLoaded('marketerEmployee', fn () => $this->marketerEmployee ? [
                'id' => $this->marketerEmployee->id, 'name' => $this->marketerEmployee->name,
            ] : null), 'stage' => $this->stage, 'probability' => $this->probability, 'probability_is_manual' => $this->probability_is_manual,
            'estimated_value' => $this->estimated_value, 'next_follow_up_at' => $this->next_follow_up_at?->toDateString(),
            'notes' => $this->notes, 'converted_at' => $this->converted_at?->toISOString(),
            'owner' => $this->whenLoaded('owner', fn () => $this->owner?->only(['id', 'name'])),
            'interactions' => CrmInteractionResource::collection($this->whenLoaded('interactions'))];
    }
}
