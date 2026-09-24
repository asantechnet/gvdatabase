<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contract_id' => $this->contract_id,
            'service_id' => $this->service_id,
            'service' => new ServiceResource($this->whenLoaded('service')),
            'fee' => $this->fee,
            'currency_id' => $this->currency_id,
            'currency' => $this->whenLoaded('currency'),
            'applicant_id' => $this->applicant_id,
            'customer_package_id' => $this->customer_package_id,
            'sold_by_employee_id' => $this->sold_by_employee_id,
            'marketer_id' => $this->marketer_id,
            'outsourcing_company_id' => $this->outsourcing_company_id,
            'outsourcing_percentage' => $this->outsourcing_percentage,
            'country_id' => $this->country_id,
            'education_level_id' => $this->education_level_id,
            'language_id' => $this->language_id,
            'notes' => $this->notes,
            'status' => $this->contract?->status,
            'contract_number' => $this->contract?->contract_number,
            'sold_by' => $this->whenLoaded('soldByEmployee', fn () => $this->soldByEmployee ? ['id' => $this->soldByEmployee->id, 'name' => $this->soldByEmployee->name] : null),
            'marketer' => $this->whenLoaded('marketer', fn () => $this->marketer ? ['id' => $this->marketer->id, 'name' => $this->marketer->name] : null),
            'delivery_employees' => $this->whenLoaded('deliveryEmployees', fn () => $this->deliveryEmployees->map(fn ($employee) => ['id' => $employee->id, 'name' => $employee->name])->values()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
