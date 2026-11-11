<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AcademyClassResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $seats = $this->seats_taken
            ?? $this->active_enrollments_count
            ?? ($this->relationLoaded('enrollments') ? $this->enrollments->where('status', 'active')->count() : null);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'program' => $this->whenLoaded('program', fn () => $this->program ? [
                'id' => $this->program->id, 'name' => $this->program->name,
            ] : null),
            'teachers' => $this->whenLoaded('teachers', fn () => $this->teachers->map(fn ($teacher) => [
                'id' => $teacher->id, 'name' => $teacher->name, 'avatar' => $teacher->avatar,
            ])->values()->all()),
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'weekdays' => $this->weekdays ?? [],
            'starts_at' => $this->starts_at ? substr((string) $this->starts_at, 0, 5) : null,
            'ends_at' => $this->ends_at ? substr((string) $this->ends_at, 0, 5) : null,
            'capacity' => $this->capacity,
            'seats_taken' => $seats !== null ? (int) $seats : null,
            'fee' => $this->fee !== null ? (float) $this->fee : null,
        ];
    }
}
