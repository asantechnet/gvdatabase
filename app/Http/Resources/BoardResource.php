<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BoardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $userId = $request->user()?->id;

        return [
            'id' => (string) $this->id,
            'title' => $this->title,
            'order' => (int) $this->order,
            // A non-null owner marks a private personal board.
            'user_id' => $this->user_id === null ? null : (string) $this->user_id,
            'is_personal' => $this->user_id !== null,
            'is_owned' => $this->user_id !== null && (int) $this->user_id === (int) $userId,
            'members' => UserResource::collection($this->whenLoaded('users')),
            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),
        ];
    }
}
