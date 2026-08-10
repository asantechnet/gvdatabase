<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorthyEmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'employeeId' => (string) $this->employee_id,
            'employeeName' => $this->employee->name,
            'employeeEmail' => $this->employee->user?->email,
            'department' => $this->employee->department,
            'performanceScore' => $this->performance_score,
            'eligibilityReason' => $this->eligibility_reason,
            'achievementDetails' => $this->achievement_details,
            'suggestedAmount' => $this->suggested_amount,
            'selectedAmount' => $this->selected_amount,
            'selectedType' => $this->selected_type,
            'isGranted' => $this->is_granted,
            'bonusMonth' => $this->bonus_month->format('Y-m'),
        ];
    }
}
