<?php

namespace App\Http\Controllers;

use App\Models\Position;
use App\Models\Currency;
use App\Http\Resources\PositionResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PositionController extends Controller
{
    public function index(): JsonResponse
    {
        $positions = Position::query()
            ->withCount(['employees', 'users'])
            ->orderBy('title')
            ->get();
        return response()->json([
            'success' => true,
            'data' => PositionResource::collection($positions)
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255|unique:positions,title',
            'salary' => 'required|numeric|min:0',
            'status' => 'required|in:active,inactive',
            'description' => 'nullable|string',
        ]);

        $position = Position::create([...$data, ...$this->currencySnapshot()]);

        return response()->json([
            'success' => true,
            'data' => new PositionResource($position->loadCount(['employees', 'users']))
        ], 201);
    }

    public function show($id)
    {
        $position = Position::withCount(['employees', 'users'])->find($id);
        if (!$position) {
            return response()->json([
                'success' => false,
                'message' => 'Position not found'
            ], 404);
        }

        if ($position->system_key && ($request->has('title') || $request->has('status'))) {
            return response()->json([
                'success' => false,
                'code' => 'business.system_position_locked',
            ], 422);
        }
        return response()->json([
            'success' => true,
            'data' => new PositionResource($position->loadCount(['employees', 'users']))
        ]);
    }

    public function update(Request $request, $id)
    {
        $position = Position::find($id);
        if (!$position) {
            return response()->json([
                'success' => false,
                'message' => 'Position not found'
            ], 404);
        }

        if ($position->system_key) {
            return response()->json([
                'success' => false,
                'code' => 'business.system_position_cannot_be_deleted',
            ], 422);
        }

        $data = $request->validate([
            'title' => 'sometimes|string|max:255|unique:positions,title,'.$id,
            'salary' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|in:active,inactive',
            'description' => 'nullable|string',
        ]);

        // A position's suggested salary is always denominated in the current
        // company base currency, with its own display snapshot.
        $position->update([...$data, ...$this->currencySnapshot()]);

        return response()->json([
            'success' => true,
            'data' => new PositionResource($position->loadCount(['employees', 'users']))
        ]);
    }

    public function destroy($id)
    {
        $position = Position::find($id);
        if (!$position) {
            return response()->json([
                'success' => false,
                'message' => 'Position not found'
            ], 404);
        }

        if ($position->employees()->exists() || $position->users()->exists()) {
            return response()->json([
                'success' => false,
                'code' => 'business.position_in_use'
            ], 422);
        }

        $position->delete();
        return response()->json([
            'success' => true,
            'message' => 'Position deleted successfully'
        ]);
    }

    private function currencySnapshot(): array
    {
        $currency = Currency::query()->where('is_base', true)->firstOrFail();

        return [
            'currency_id' => $currency->id,
            'currency_code' => $currency->code,
            'currency_symbol' => $currency->symbol,
            'currency_decimal_places' => $currency->decimal_places,
            'rate' => 1,
        ];
    }

}
