<?php

namespace App\Http\Controllers;

use App\Http\Resources\MarketerResource;
use App\Http\Resources\UserResource;
use App\Models\Customer;
use App\Models\Marketer;
use App\Models\Visitor;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MarketerController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => MarketerResource::collection(Marketer::with('user')->orderBy('name')->get())]);
    }

    public function accountOptions(): AnonymousResourceCollection
    {
        return UserResource::collection(
            User::query()->with(['employee:id,user_id,name', 'marketer:id,user_id,name'])->orderBy('name')->get(),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $marketer = Marketer::create($this->validated($request));
        return response()->json(['success' => true, 'data' => new MarketerResource($marketer->load('user'))], 201);
    }

    public function show(Marketer $marketer): JsonResponse
    {
        return response()->json(['success' => true, 'data' => new MarketerResource($marketer->load('user'))]);
    }

    public function update(Request $request, Marketer $marketer): JsonResponse
    {
        DB::transaction(function () use ($request, $marketer) {
            $oldName = $marketer->name;
            $marketer->update($this->validated($request, $marketer));

            if ($oldName !== $marketer->name) {
                Customer::where('marketer', $oldName)->update(['marketer' => $marketer->name]);
                Visitor::where('marketer', $oldName)->update(['marketer' => $marketer->name]);
            }
        });

        return response()->json(['success' => true, 'data' => new MarketerResource($marketer->fresh(['user']))]);
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
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at'), Rule::unique('marketers', 'user_id')->whereNull('deleted_at')->ignore($marketer?->id)],
            'name' => ['required', 'string', 'max:255', Rule::unique('marketers', 'name')->whereNull('deleted_at')->ignore($marketer?->id)],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'status' => ['required', 'in:active,inactive'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
