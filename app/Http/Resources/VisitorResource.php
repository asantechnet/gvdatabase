<?php

namespace App\Http\Resources;

use App\Support\BuiltInService;
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
            // The stored value stays visible even when it is legacy free text;
            // canonical rows also get a translation key for the UI.
            'service' => $this->service,
            'service_key' => BuiltInService::isValid($this->service) ? $this->service : null,
            'service_label' => BuiltInService::isValid($this->service) ? BuiltInService::label($this->service) : $this->service,
            'service_label_key' => BuiltInService::isValid($this->service) ? BuiltInService::labelKey($this->service) : null,
            'country_id' => $this->country_id,
            'country' => $this->whenLoaded('country', fn () => $this->country?->only(['id', 'name', 'code'])),
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
            'consultant' => $this->whenLoaded('consultant', fn () => $this->consultant ? [
                'id' => $this->consultant->id,
                'name' => $this->consultant->name,
                'avatar' => $this->consultant->avatar,
            ] : null),
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
