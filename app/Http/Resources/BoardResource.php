<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BoardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'title' => $this->title,
            'order' => (int) $this->order,
            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),
        ];
    }
}
