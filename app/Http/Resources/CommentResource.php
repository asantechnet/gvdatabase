<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'taskId' => (string) $this->task_id,
            'senderName' => $this->user->name ?? 'Unknown User',
            'senderId' => (string) $this->user_id,
            'content' => $this->content,
            'timestamp' => $this->created_at ? $this->created_at->format('g:i A') : '',
            'createdAt' => $this->created_at ? $this->created_at->toIso8601String() : null,
        ];
    }
}
