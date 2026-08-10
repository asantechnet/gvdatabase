<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'boardId' => (string) $this->board_id,
            'title' => $this->title,
            'description' => $this->description ?? '',
            'priority' => $this->priority,
            'progress' => (int) $this->progress,
            'startDate' => $this->start_date ? Carbon::parse($this->start_date)->format('Y-m-d') : '',
            'dueDate' => $this->due_date ? Carbon::parse($this->due_date)->format('Y-m-d') : '',
            'order' => (int) $this->order,

            // Relational collections (loaded dynamically)
            'assignees' => UserResource::collection($this->whenLoaded('users')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'subtasks' => SubtaskResource::collection($this->whenLoaded('subtasks')),
            'comments' => CommentResource::collection($this->whenLoaded('comments')),

            // Pluck dependency IDs as string array for easy frontend lookup
            'dependencies' => $this->whenLoaded('dependencies', function () {
                return $this->dependencies->pluck('id')->map(fn ($id) => (string) $id);
            }, []),
        ];
    }
}
