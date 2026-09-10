<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'boardId' => (string) $this->board_id,
            'customerId' => $this->customer_id,
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? ['id' => $this->customer->id, 'name' => $this->customer->name, 'company' => $this->customer->company] : null),
            'title' => $this->title,
            'description' => $this->description ?? '',
            'results' => $this->results ?? '',
            'priority' => $this->priority,
            'progress' => (int) $this->progress,
            'startDate' => $this->start_date ? Carbon::parse($this->start_date)->format('Y-m-d') : '',
            'dueDate' => $this->due_date ? Carbon::parse($this->due_date)->format('Y-m-d') : '',
            'completedAt' => $this->completed_at?->toISOString(),
            'reopenedCount' => (int) $this->reopened_count,
            'rejectedCount' => (int) $this->rejected_count,
            'managerQualityScore' => $this->manager_quality_score === null ? null : (float) $this->manager_quality_score,
            'order' => (int) $this->order,

            // Relational collections (loaded dynamically)
            'assignees' => UserResource::collection($this->whenLoaded('users')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'services' => ServiceResource::collection($this->whenLoaded('services')),
            'subtasks' => SubtaskResource::collection($this->whenLoaded('subtasks')),
            'comments' => CommentResource::collection($this->whenLoaded('comments')),
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($attachment) => [
                'id' => (string) $attachment->id,
                'section' => $attachment->section,
                'name' => $attachment->original_name,
                'mimeType' => $attachment->mime_type,
                'size' => (int) $attachment->size,
                'uploadedAt' => $attachment->created_at?->toISOString(),
                'downloadUrl' => "/api/tasks/{$this->id}/attachments/{$attachment->id}/download",
                'url' => "/api/tasks/{$this->id}/attachments/{$attachment->id}/download",
            ]), []),

            // Pluck dependency IDs as string array for easy frontend lookup
            'dependencies' => $this->whenLoaded('dependencies', function () {
                return $this->dependencies->pluck('id')->map(fn ($id) => (string) $id);
            }, []),
        ];
    }
}
