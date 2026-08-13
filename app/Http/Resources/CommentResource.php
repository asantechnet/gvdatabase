<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Policies\CommentPolicy;
use App\Support\EditWindow;

class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $editableUntil = app(EditWindow::class)->editableUntil($this->resource);
        $canEdit = $request->user()
            && app(CommentPolicy::class)->update($request->user(), $this->resource)
            && app(EditWindow::class)->canEdit($request->user(), $this->resource);

        return [
            'id' => (string) $this->id,
            'taskId' => (string) $this->task_id,
            'senderName' => $this->user->name ?? 'Unknown User',
            'senderId' => (string) $this->user_id,
            'content' => $this->content,
            'timestamp' => $this->created_at ? $this->created_at->format('g:i A') : '',
            'createdAt' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'editableUntil' => $editableUntil->toISOString(),
            'canEdit' => (bool) $canEdit,
            'editWindowExpired' => ! app(EditWindow::class)->isOpen($this->resource),
        ];
    }
}
