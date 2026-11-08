<?php

namespace App\Http\Controllers;

use App\Models\Duration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcademyCatalogController extends Controller
{
    public function durations(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Duration::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'months', 'is_active', 'sort_order']),
        ]);
    }

    public function updateDuration(Request $request, Duration $duration): JsonResponse
    {
        $data = $request->validate([
            'months' => ['nullable', 'integer', 'between:1,60'],
        ]);

        $duration->update($data);

        return response()->json([
            'success' => true,
            'data' => $duration->only(['id', 'name', 'months', 'is_active', 'sort_order']),
        ]);
    }
}
