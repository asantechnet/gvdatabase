<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class FileController extends Controller
{
    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240', // 10MB max
            'folder' => 'sometimes|string|max:100',
        ]);

        $validator->validate();

        $file = $request->file('file');
        $folder = $request->input('folder', 'uploads');
        
        $fileName = time() . '_' . $file->getClientOriginalName();
        $filePath = $file->storeAs($folder, $fileName, 'public');

        return response()->json([
            'success' => true,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'file_size' => $file->getSize(),
            'file_type' => $file->getClientMimeType(),
        ], 201);
    }

    public function download($filePath)
    {
        if (!Storage::exists('public/' . $filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'File not found'
            ], 404);
        }

        return Storage::download('public/' . $filePath);
    }

    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_path' => 'required|string',
        ]);

        $validator->validate();

        $filePath = $request->input('file_path');
        
        if (!Storage::exists('public/' . $filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'File not found'
            ], 404);
        }

        Storage::delete('public/' . $filePath);

        return response()->json([
            'success' => true,
            'message' => 'File deleted successfully'
        ]);
    }

    public function listFiles(Request $request)
    {
        $folder = $request->input('folder', 'uploads');
        $files = Storage::files('public/' . $folder);
        
        $fileList = [];
        foreach ($files as $file) {
            $fileList[] = [
                'path' => str_replace('public/', '', $file),
                'name' => basename($file),
                'size' => Storage::size($file),
                'last_modified' => Storage::lastModified($file),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $fileList
        ]);
    }

    public function viewFile($filePath)
    {
        if (!Storage::exists('public/' . $filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'File not found'
            ], 404);
        }

        $file = Storage::get('public/' . $filePath);
        $mimeType = Storage::mimeType('public/' . $filePath);

        return response($file, 200, [
            'Content-Type' => $mimeType,
        ]);
    }
}
