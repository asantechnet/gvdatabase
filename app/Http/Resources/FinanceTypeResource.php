<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinanceTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'kind' => $this->kind, 'value' => $this->value, 'name' => $this->name, 'is_system' => $this->is_system];
    }
}
