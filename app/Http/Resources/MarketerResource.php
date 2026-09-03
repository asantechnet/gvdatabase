<?php

namespace App\Http\Resources;

use App\Models\Customer;
use App\Models\Visitor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'notes' => $this->notes,
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'acc_status' => $this->user->acc_status,
            ] : null),
            'customer_count' => Customer::where('marketer', $this->name)->count(),
            'visitor_count' => Visitor::where('marketer', $this->name)->count(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
