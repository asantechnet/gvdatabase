<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TaskAttachmentController extends Controller
{
    public function store(Request $request, Task $task)
    {
        Gate::authorize('update', $task);
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'section' => ['nullable', 'in:input,results'],
        ]);

        $file = $validated['file'];
        $disk = 'public';
        $path = $file->storeAs("task-attachments/{$task->id}", Str::uuid().'.'.$file->extension(), $disk);
        $attachment = $task->attachments()->create([
            'user_id' => $request->user()->id,
            'section' => $validated['section'] ?? 'results',
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        return response()->json(['data' => $this->attachmentData($attachment)], 201);
    }

    public function download(Task $task, TaskAttachment $attachment)
    {
        $this->ensureBelongsToTask($task, $attachment);
        Gate::authorize('view', $task);

        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function destroy(Task $task, TaskAttachment $attachment)
    {
        $this->ensureBelongsToTask($task, $attachment);
        Gate::authorize('update', $task);
        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();

        return response()->noContent();
    }

    private function ensureBelongsToTask(Task $task, TaskAttachment $attachment): void
    {
        abort_unless($attachment->task_id === $task->id, 404);
    }

    private function attachmentData(TaskAttachment $attachment): array
    {
        return [
            'id' => (string) $attachment->id,
            'section' => $attachment->section,
            'name' => $attachment->original_name,
            'mimeType' => $attachment->mime_type,
            'size' => $attachment->size,
            'uploadedAt' => $attachment->created_at?->toISOString(),
            'downloadUrl' => "/api/tasks/{$attachment->task_id}/attachments/{$attachment->id}/download",
            'url' => Storage::disk($attachment->disk)->url($attachment->path),
        ];
    }
}
