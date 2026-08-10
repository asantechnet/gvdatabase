<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'father_name' => $this->father_name,
            'contact' => $this->contact,
            'email' => $this->user?->email,
            'education_lvl' => $this->education_lvl,
            'position' => $this->position?->title,
            'role' => $this->position?->title,
            'position_id' => $this->position_id,
            'salary' => (float) $this->salary,
            'bonus' => $this->bonus,
            'addr' => $this->addr,
            'ID_file' => $this->ID_file,
            'assure_file' => $this->assure_file,
            'avatar' => $this->avatar,
            'join_date' => $this->join_date->format('Y-m-d'),
            'contract_duration' => $this->contract_duration,
        ];
    }
}
