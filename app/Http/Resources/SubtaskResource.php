<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubtaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'taskId' => (string) $this->task_id,
            'title' => $this->title,
            'category' => $this->category ?? 'General',
            'priority' => $this->priority ?? 'medium',
            'isCompleted' => (bool) $this->is_completed,
        ];
    }
}
