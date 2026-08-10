<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class AttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'staff_id' => $this->staff_id,
            'name' => $this->staff?->name ?? 'N/A',
            'role' => $this->staff?->position?->title ?? 'N/A',
            'date' => $this->date->format('Y-m-d'),
            'status' => $this->status,
            'timestamp' => Carbon::parse($this->logged_at)->format('h:i A'),
        ];
    }
}
