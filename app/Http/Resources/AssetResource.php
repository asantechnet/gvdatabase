<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'serial_number' => $this->serial_number,
            'item' => $this->item,
            'quantity' => $this->quantity,
            'price' => (float) $this->price,
            'currency_id' => $this->currency_id,
            'currency' => $this->whenLoaded('currency', fn () => $this->currency ? new CurrencyResource($this->currency) : null),
            'acquisition_date' => $this->acquisition_date?->toDateString(),
            'useful_life_years' => $this->useful_life_years,
            'salvage_value' => (float) $this->salvage_value,
            'total_value' => $this->totalValue(),
            'book_value' => $this->bookValue(),
            'model' => $this->model,
            'condition' => $this->condition,
            'office_id' => $this->office_id,
            'office' => $this->whenLoaded('office', fn () => [
                'id' => $this->office->id,
                'name' => $this->office->name,
                'code' => $this->office->code,
                'status' => $this->office->status,
            ]),
            'allocated_quantity' => $this->whenLoaded('allocations', fn () => $this->allocations->sum('quantity')),
            'available_quantity' => $this->whenLoaded('allocations', fn () => max(0, $this->quantity - $this->allocations->sum('quantity'))),
            'allocations' => $this->whenLoaded('allocations', fn () => $this->allocations->map(fn ($allocation) => [
                'id' => $allocation->id,
                'employee_id' => $allocation->employee_id,
                'employee' => $allocation->employee ? ['id' => $allocation->employee->id, 'name' => $allocation->employee->name] : null,
                'quantity' => $allocation->quantity,
            ])->values()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
