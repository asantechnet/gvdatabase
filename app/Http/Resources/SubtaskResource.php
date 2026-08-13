<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Policies\SubtaskPolicy;
use App\Support\EditWindow;

class SubtaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $editableUntil = app(EditWindow::class)->editableUntil($this->resource);
        $canEdit = $request->user()
            && app(SubtaskPolicy::class)->update($request->user(), $this->resource)
            && app(EditWindow::class)->canEdit($request->user(), $this->resource);

        return [
            'id' => (string) $this->id,
            'taskId' => (string) $this->task_id,
            'userId' => (string) $this->user_id,
            'title' => $this->title,
            'category' => $this->category ?? 'General',
            'priority' => $this->priority ?? 'medium',
            'isCompleted' => (bool) $this->is_completed,
            'createdAt' => $this->created_at?->toISOString(),
            'editableUntil' => $editableUntil->toISOString(),
            'canEdit' => (bool) $canEdit,
            'editWindowExpired' => ! app(EditWindow::class)->isOpen($this->resource),
        ];
    }
}
