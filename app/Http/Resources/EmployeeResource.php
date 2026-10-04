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
            'id_number' => $this->id_number,
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
            'department_id' => $this->department_id,
            'department_name' => $this->departmentRecord?->name ?? $this->department,
            'supervisor_id' => $this->supervisor_id,
            'supervisor_name' => $this->supervisor?->name,
            'salary' => $this->salary !== null ? (float) $this->salary : null,
            'commissions' => $this->whenLoaded('commissionServices', fn () => $this->commissionServices->map(fn ($service) => [
                'service_id' => $service->id,
                'service' => $service->type,
                'fee' => (float) $service->fee,
                'currency' => $service->currency ? [
                    'id' => $service->currency->id,
                    'code' => $service->currency->code,
                    'symbol' => $service->currency->symbol,
                    'decimalPlaces' => $service->currency->decimal_places,
                ] : null,
                'percentage' => (float) $service->pivot->percentage,
            ])),
            'commission_earned_base' => $this->commissionEarnedBase(),
            'commission_balances' => $this->employment_type === 'commission'
                ? $this->commissionAccruals()->where('status', '!=', 'void')
                    ->selectRaw('base_currency_id, status, SUM(commission_base_amount) as amount')
                    ->groupBy('base_currency_id', 'status')->get()->map(fn ($row) => [
                        'base_currency_id' => $row->base_currency_id,
                        'status' => $row->status,
                        'amount' => (float) $row->amount,
                    ])->values()
                : [],
            'total_worked_hours' => (float) $this->total_worked_hours,
            'addr' => $this->addr,
            'avatar' => $this->avatar,
            'document' => $this->document_path ? ['name' => $this->document_name, 'mimeType' => $this->document_mime, 'size' => $this->document_size, 'downloadUrl' => "/api/employees/{$this->id}/document"] : null,
            'attachments' => $this->attachmentsPayload('employees', $this->id),
            'join_date' => $this->join_date->format('Y-m-d'),
            'contract_duration' => $this->contract_duration,
        ];
    }
}
