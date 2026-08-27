<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'method' => $this->method,
            'instruction' => $this->instruction,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
