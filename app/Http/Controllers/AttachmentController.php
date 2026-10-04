<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Income;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * One shared implementation for the attachment sections on expenses, incomes
 * and staff. It mirrors the task-attachment contract (multiple files, optional
 * section, the same accepted formats) so every feature behaves identically.
 */
class AttachmentController extends Controller
{
    /** @var array<string, array{model: class-string<Model>, view: string, edit: string}> */
    private const RESOURCES = [
        'expenses' => ['model' => Expense::class, 'view' => 'view_expenses', 'edit' => 'edit_expenses'],
        'incomes' => ['model' => Income::class, 'view' => 'view_incomes', 'edit' => 'edit_incomes'],
        'employees' => ['model' => Employee::class, 'view' => 'view_employees', 'edit' => 'edit_employees'],
    ];

    public function index(Request $request, string $type, int $id)
    {
        $record = $this->record($request->user(), $type, $id, false);

        return response()->json([
            'data' => $record->attachments()->latest('id')->get()->map(fn (Attachment $a) => $this->data($a, $type, $id))->values(),
        ]);
    }

    public function store(Request $request, string $type, int $id)
    {
        $record = $this->record($request->user(), $type, $id, true);

        $validated = $request->validate([
            'file' => [
                'required', 'file', 'max:10240',
                'mimes:pdf,txt,csv,doc,docx,xls,xlsx,png,jpg,jpeg,webp',
            ],
            'section' => ['nullable', 'in:input,results'],
        ]);

        $file = $validated['file'];
        $disk = 'local';
        $path = $file->storeAs("attachments/{$type}/{$id}", Str::uuid().'.'.$file->extension(), $disk);

        try {
            /** @var Attachment $attachment */
            $attachment = $record->attachments()->create([
                'user_id' => $request->user()->id,
                'section' => $validated['section'] ?? null,
                'disk' => $disk,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }

        return response()->json(['data' => $this->data($attachment, $type, $id)], 201);
    }

    public function download(Request $request, string $type, int $id, Attachment $attachment)
    {
        $this->record($request->user(), $type, $id, false);
        $this->ensureBelongsTo($attachment, $type, $id);

        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function destroy(Request $request, string $type, int $id, Attachment $attachment)
    {
        $this->record($request->user(), $type, $id, true);
        $this->ensureBelongsTo($attachment, $type, $id);

        $attachment->delete();
        Storage::disk($attachment->disk)->delete($attachment->path);

        return response()->noContent();
    }

    private function record(User $user, string $type, int $id, bool $write): Model
    {
        $config = self::RESOURCES[$type] ?? abort(404);
        abort_unless($user->hasPermission($write ? $config['edit'] : $config['view']), 403, 'auth.forbidden');

        $modelClass = $config['model'];

        return $modelClass::query()->findOrFail($id);
    }

    private function ensureBelongsTo(Attachment $attachment, string $type, int $id): void
    {
        $config = self::RESOURCES[$type] ?? abort(404);
        $modelClass = $config['model'];
        $expected = (new $modelClass)->getMorphClass();

        abort_unless(
            (int) $attachment->attachable_id === $id && $attachment->attachable_type === $expected,
            404,
        );
    }

    /** @return array<string, mixed> */
    private function data(Attachment $attachment, string $type, int $id): array
    {
        return [
            'id' => (string) $attachment->id,
            'section' => $attachment->section,
            'name' => $attachment->original_name,
            'mimeType' => $attachment->mime_type,
            'size' => (int) $attachment->size,
            'uploadedAt' => $attachment->created_at?->toISOString(),
            'downloadUrl' => "/api/attachments/{$type}/{$id}/{$attachment->id}/download",
        ];
    }
}
