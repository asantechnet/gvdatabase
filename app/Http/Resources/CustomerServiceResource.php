<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_id' => $this->service_id,
            'customer_id' => $this->customer_id,
            'employee_id' => $this->employee_id,
            'employee' => $this->whenLoaded('employee', fn () => $this->employee ? [
                'id' => $this->employee->id,
                'name' => $this->employee->name,
            ] : null),
            'marketer_id' => $this->marketer_id,
            'marketer' => $this->whenLoaded('marketer', fn () => $this->marketer ? [
                'id' => $this->marketer->id,
                'name' => $this->marketer->name,
            ] : null),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'company' => $this->customer->company,
            ] : null),
            'type' => $this->service->type,
            'fee' => (float) ($this->fee ?? $this->service->fee),
            'currency_id' => $this->currency_id,
            'currency' => new CurrencyResource($this->currency ?? $this->service->currency),
            'deadline' => $this->deadline?->toDateString(),
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
