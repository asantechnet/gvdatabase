<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'message' => $this->message,
            'date_time' => $this->date_time?->toISOString(),
            'is_read' => $this->is_read,
            'employee_id' => $this->employee_id,
            'go_to_url' => $this->go_to_url,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
