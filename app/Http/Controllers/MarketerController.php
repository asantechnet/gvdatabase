<?php

namespace App\Http\Controllers;

use App\Http\Resources\MarketerResource;
use App\Models\Customer;
use App\Models\Marketer;
use App\Models\Visitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MarketerController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => MarketerResource::collection(Marketer::orderBy('name')->get())]);
    }

    public function store(Request $request): JsonResponse
    {
        $marketer = Marketer::create($this->validated($request));
        return response()->json(['success' => true, 'data' => new MarketerResource($marketer)], 201);
    }

    public function show(Marketer $marketer): JsonResponse
    {
        return response()->json(['success' => true, 'data' => new MarketerResource($marketer)]);
    }

    public function update(Request $request, Marketer $marketer): JsonResponse
    {
        $oldName = $marketer->name;
        $marketer->update($this->validated($request, $marketer));

        if ($oldName !== $marketer->name) {
            Customer::where('marketer', $oldName)->update(['marketer' => $marketer->name]);
            Visitor::where('marketer', $oldName)->update(['marketer' => $marketer->name]);
        }

        return response()->json(['success' => true, 'data' => new MarketerResource($marketer->fresh())]);
    }

    public function destroy(Marketer $marketer): JsonResponse
    {
        if (Customer::where('marketer', $marketer->name)->exists() || Visitor::where('marketer', $marketer->name)->exists()) {
            return response()->json(['success' => false, 'code' => 'business.marketer_in_use'], 409);
        }

        $marketer->delete();
        return response()->json(['success' => true, 'message' => 'Marketer deleted.']);
    }

    private function validated(Request $request, ?Marketer $marketer = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('marketers', 'name')->whereNull('deleted_at')->ignore($marketer?->id)],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'status' => ['required', 'in:active,inactive'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
