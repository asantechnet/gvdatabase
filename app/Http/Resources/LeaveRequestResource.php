<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $duration = (float) $this->duration_days;
        $offDays = (float) ($this->off_day_days ?? 0);

        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'date' => $this->date->format('Y-m-d'),
            'year' => $this->year,
            'duration_days' => $duration,
            'reason' => $this->reason,
            'is_granted' => $this->is_granted,
            'off_day_days' => $offDays,
            'absent_days' => $this->is_granted ? max(0, $duration - $offDays) : 0,
            'off_day_override' => (bool) $this->off_day_override,
            // What the request means for pay once it is approved.
            'treatment' => ! $this->is_granted
                ? 'pending'
                : ($offDays >= $duration ? 'off_day' : ($offDays > 0 ? 'partial' : 'absent')),
            'approval_note' => $this->approval_note,
            'granted_at' => $this->granted_at?->toISOString(),
            'approved_by' => $this->granted_by,
            'approvedBy' => $this->whenLoaded('grantor', fn () => $this->grantor ? [
                'id' => $this->grantor->id,
                'name' => $this->grantor->name,
            ] : null),
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee->id,
                'name' => $this->employee->name,
                'avatar' => $this->employee->avatar,
                'position' => $this->employee->position?->title,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
