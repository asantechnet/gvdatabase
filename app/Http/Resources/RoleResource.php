<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_protected' => in_array($this->name, ['super_admin', 'applicant'], true),
            'permissions' => $this->relationLoaded('permissions')
                ? $this->permissions->pluck('name')->values()
                : [],
            'users_count' => $this->whenCounted('users'),
            'users' => $this->whenLoaded('users', $this->users->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'employee' => $user->employee ? ['avatar' => $user->employee->avatar] : null,
            ])),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
