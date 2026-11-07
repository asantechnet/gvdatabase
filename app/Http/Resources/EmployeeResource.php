<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\PresentsAttachments;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    use PresentsAttachments;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'father_name' => $this->father_name,
            'contact' => $this->contact,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'id_number' => $this->id_number,
            'bank_account_number' => $this->bank_account_number,
            'guarantor_name' => $this->guarantor_name,
            'guarantor_id_number' => $this->guarantor_id_number,
            'guarantor_phone' => $this->guarantor_phone,
            'guarantor_address' => $this->guarantor_address,
            'guarantor_licence' => $this->guarantor_licence,
            'email' => $this->user?->email,
            'education_lvl' => $this->education_lvl,
            'position' => $this->position?->title,
            'role' => $this->position?->title,
            'position_id' => $this->position_id,
            'status' => $this->status,
            'office_id' => $this->office_id,
            'office' => $this->office ? ['id' => $this->office->id, 'name' => $this->office->name, 'code' => $this->office->code] : null,
            'department' => $this->department,
            'department_id' => $this->department_id,
            'department_name' => $this->departmentRecord?->name ?? $this->department,
            'departments' => $this->whenLoaded('departments', fn () => $this->departments
                ->map(fn ($department) => [
                    'id' => $department->id,
                    'name' => $department->name,
                    'is_primary' => (bool) $department->pivot->is_primary,
                ])
                ->values()),
            // Fall back to the legacy primary so an endpoint that did not
            // eager-load memberships never emits an empty list for a member.
            'department_ids' => $this->relationLoaded('departments')
                ? $this->departments->pluck('id')->map(fn ($id) => (int) $id)->values()
                : array_values(array_filter([$this->department_id])),
            'primary_department_id' => $this->relationLoaded('departments')
                ? $this->departments->first(fn ($department) => (bool) $department->pivot->is_primary)?->id
                : $this->department_id,
            'supervisor_id' => $this->supervisor_id,
            'supervisor_name' => $this->supervisor?->name,
            'salary' => $this->salary !== null ? (float) $this->salary : null,
            'is_commission_based' => (bool) $this->is_commission_based,
            'service_commissions' => $this->whenLoaded('serviceCommissions', fn () => $this->serviceCommissions
                ->map(fn ($rate) => ['service' => $rate->service, 'percentage' => (float) $rate->percentage])
                ->values()),
            'total_worked_hours' => (float) $this->total_worked_hours,
            'addr' => $this->addr,
            'avatar' => $this->avatar,
            'document' => $this->document_path ? ['name' => $this->document_name, 'mimeType' => $this->document_mime, 'size' => $this->document_size, 'downloadUrl' => "/api/employees/{$this->id}/document"] : null,
            'attachments' => $this->attachmentsPayload('employees', $this->id),
            'join_date' => $this->join_date->format('Y-m-d'),
            'contract_duration' => $this->contract_duration,
            'off_day_allowance_days' => $this->off_day_allowance_days !== null ? (float) $this->off_day_allowance_days : null,
        ];
    }
}
