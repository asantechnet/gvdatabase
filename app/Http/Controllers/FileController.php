<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileController extends Controller
{
    private const DISK = 'local';

    private const ROOT = 'managed-files';

    public function upload(Request $request)
    {
        $validated = $request->validate([
            'file' => [
                'required', 'file', 'max:10240',
                'mimes:pdf,txt,csv,doc,docx,xls,xlsx,png,jpg,jpeg,webp',
            ],
            'folder' => ['sometimes', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);

        $file = $validated['file'];
        $folder = $validated['folder'] ?? 'uploads';
        $extension = strtolower($file->extension());
        $fileName = Str::uuid().($extension !== '' ? ".{$extension}" : '');
        $filePath = $file->storeAs(self::ROOT."/{$folder}", $fileName, self::DISK);

        abort_unless(is_string($filePath), 500, 'business.file_storage_failed');

        return response()->json([
            'success' => true,
            'file_path' => $filePath,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'file_type' => $file->getMimeType(),
        ], 201);
    }

    public function download(Request $request)
    {
        $path = $this->validatedPath($request);
        abort_unless(Storage::disk(self::DISK)->exists($path), 404, 'resource.file_not_found');

        return Storage::disk(self::DISK)->download($path);
    }

    public function delete(Request $request)
    {
        $path = $this->validatedPath($request);
        abort_unless(Storage::disk(self::DISK)->exists($path), 404, 'resource.file_not_found');
        abort_unless(Storage::disk(self::DISK)->delete($path), 500, 'business.file_delete_failed');

        return response()->json(['success' => true, 'message' => 'File deleted successfully']);
    }

    public function listFiles(Request $request)
    {
        $validated = $request->validate([
            'folder' => ['sometimes', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);
        $folder = $validated['folder'] ?? 'uploads';
        $disk = Storage::disk(self::DISK);
        $files = $disk->files(self::ROOT."/{$folder}");

        return response()->json([
            'success' => true,
            'data' => collect($files)->map(fn (string $file): array => [
                'path' => $file,
                'name' => basename($file),
                'size' => $disk->size($file),
                'last_modified' => $disk->lastModified($file),
            ])->values(),
        ]);
    }

    public function viewFile(Request $request)
    {
        $path = $this->validatedPath($request);
        $disk = Storage::disk(self::DISK);
        abort_unless($disk->exists($path), 404, 'resource.file_not_found');

        return response()->stream(function () use ($disk, $path): void {
            $stream = $disk->readStream($path);
            abort_unless(is_resource($stream), 500, 'business.file_read_failed');
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $disk->mimeType($path) ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.addslashes(basename($path)).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function validatedPath(Request $request): string
    {
        $validated = $request->validate(['file_path' => ['required', 'string', 'max:1024']]);
        $path = str_replace('\\', '/', trim($validated['file_path'], '/'));

        abort_if($path === '' || str_contains($path, '..') || ! str_starts_with($path, self::ROOT.'/'), 422, 'business.file_path_invalid');

        return $path;
    }
}
