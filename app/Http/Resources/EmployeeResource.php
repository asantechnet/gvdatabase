<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'father_name' => $this->father_name,
            'contact' => $this->contact,
            'id_number' => $this->id_number,
            'tax_number' => $this->tax_number,
            'bank_account_number' => $this->bank_account_number,
            'guarantor_id_number' => $this->guarantor_id_number,
            'guarantor_phone' => $this->guarantor_phone,
            'guarantor_address' => $this->guarantor_address,
            'guarantor_licence' => $this->guarantor_licence,
            'email' => $this->user?->email,
            'education_lvl' => $this->education_lvl,
            'position' => $this->position?->title,
            'role' => $this->position?->title,
            'position_id' => $this->position_id,
            'employment_type' => $this->employment_type,
            'office_id' => $this->office_id,
            'office' => $this->office ? ['id' => $this->office->id, 'name' => $this->office->name, 'code' => $this->office->code] : null,
            'department' => $this->department,
            'salary' => (float) $this->salary,
            'commissions' => $this->whenLoaded('commissionServices', fn () => $this->commissionServices->map(fn ($service) => [
                'service_id' => $service->id,
                'service' => $service->type,
                'percentage' => (float) $service->pivot->percentage,
            ])),
            'commission_earned_base' => $this->commissionEarnedBase(),
            'total_worked_hours' => (float) $this->total_worked_hours,
            'addr' => $this->addr,
            'ID_file' => $this->ID_file,
            'assure_file' => $this->assure_file,
            'avatar' => $this->avatar,
            'join_date' => $this->join_date->format('Y-m-d'),
            'contract_duration' => $this->contract_duration,
        ];
    }
}
