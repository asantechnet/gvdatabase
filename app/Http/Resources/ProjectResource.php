<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'applicant_id' => $this->applicant_id,
            'applicant' => $this->whenLoaded('applicant', fn () => $this->applicant ? ['id' => $this->applicant->id, 'name' => $this->applicant->name, 'company' => $this->applicant->company] : null),
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'start_date' => $this->start_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', fn () => ['id' => $this->creator->id, 'name' => $this->creator->name]),
            // A project owns an explicit subset of its customer's services.
            // Do not infer these from every service on the customer record.
            'services' => ServiceResource::collection($this->whenLoaded('services')),
            'service_ids' => $this->whenLoaded('services', fn () => $this->services
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all(), []),
            'boards' => $this->whenLoaded('boards', fn () => BoardResource::collection($this->boards)),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
