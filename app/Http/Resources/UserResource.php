<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'is_primary' => (int) $this->id === (int) User::primaryRecordId(),
            'is_self' => (int) $this->id === (int) $request->user()?->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => $this->whenLoaded('employee', fn () => $this->employee?->avatar),
            'phone' => $this->phone,
            'address' => $this->address,
            'roles' => $this->roleNames(),
            'acc_status' => $this->acc_status,
            'email_verified' => $this->hasVerifiedEmail(),
            'password_setup_pending' => (bool) $this->must_set_password,
            'employee' => $this->whenLoaded('employee', fn () => $this->employee ? [
                'id' => $this->employee->id,
                'name' => $this->employee->name,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'contributionPercent' => $this->when(
                $this->pivot !== null && isset($this->pivot->contribution_percent),
                fn () => $this->pivot->contribution_percent === null ? null : (float) $this->pivot->contribution_percent,
            ),
        ];
    }
}
