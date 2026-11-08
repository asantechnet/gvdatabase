<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CrmRelationshipResource extends JsonResource
{
    public function __construct(
        $resource,
        private readonly string $entityType,
        private readonly bool $caseScoped = false,
    ) {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $relation = $this->resource;

        return [
            'type' => $this->entityType,
            'id' => $relation->getKey(),
            'name' => $relation->name,
            'contact' => $relation->contact ?? null,
            'email' => $relation->email ?? null,
            // Global profile notes never appear in a case context; a case
            // note is an interaction carrying the applicant id.
            'notes' => $this->caseScoped ? null : ($relation->notes ?? null),
            'is_active' => $relation->is_active ?? null,
            'code' => $relation->code ?? null,
            'service' => $relation->service ?? null,
            'status' => $relation->status ?? null,
            'country' => $this->entityType === 'university' && $relation->relationLoaded('country') && $relation->country
                ? ['id' => $relation->country->id, 'name' => $relation->country->name]
                : null,
            'responsible_employee' => $this->entityType === 'applicant'
                && $relation->relationLoaded('responsibleEmployee') && $relation->responsibleEmployee
                ? ['id' => $relation->responsibleEmployee->id, 'name' => $relation->responsibleEmployee->name]
                : null,
            'current_stage' => $this->entityType === 'applicant'
                && $relation->relationLoaded('currentStage') && $relation->currentStage
                ? ['id' => $relation->currentStage->id, 'key' => $relation->currentStage->key, 'name' => $relation->currentStage->name]
                : null,
            'interactions' => CrmInteractionResource::collection($this->whenLoaded('interactions')),
        ];
    }
}
