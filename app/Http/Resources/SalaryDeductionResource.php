<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalaryDeductionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'amount' => $this->amount,
            'sortOrder' => $this->sort_order,
            'isAutomatic' => (bool) $this->is_automatic,
            'code' => $this->code,
        ];
    }
}
