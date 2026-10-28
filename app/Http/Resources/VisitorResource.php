<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisitorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'contact' => $this->contact,
            'email' => $this->email,
            'service' => $this->service,
            'education_level_id' => $this->education_level_id,
            'education_level' => $this->whenLoaded('educationLevel', fn () => $this->educationLevel?->only(['id', 'name'])),
            'age' => $this->age,
            'grade_score' => $this->grade_score,
            'province_id' => $this->province_id,
            'province' => $this->whenLoaded('province', fn () => $this->province?->only(['id', 'name'])),
            'english_level_id' => $this->english_level_id,
            'english_level' => $this->whenLoaded('englishLevel', fn () => $this->englishLevel?->only(['id', 'name'])),
            'acquisition_route_id' => $this->acquisition_route_id,
            'acquisition_route' => $this->whenLoaded('acquisitionRoute', fn () => $this->acquisitionRoute?->only(['id', 'name'])),
            'consultant_employee_id' => $this->consultant_employee_id,
            'consultant' => $this->whenLoaded('consultant', fn () => $this->consultant ? $this->consultant->only(['id', 'name']) : null),
            'consultant_review' => $this->consultant_review,
            'notes' => $this->notes,
            'converted_at' => $this->converted_at?->toISOString(),
            'follow_up' => (bool) $this->follow_up,
            'interactions' => CrmInteractionResource::collection($this->whenLoaded('interactions')),
            'date' => $this->date->format('Y-m-d'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
