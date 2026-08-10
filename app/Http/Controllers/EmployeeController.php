<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use App\Http\Resources\EmployeeResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        // Ordering by latest and paginating is standard practice for lists
        return EmployeeResource::collection(Employee::with(['position', 'user'])->get());
    }

    public function store(Request $request): EmployeeResource
    {
        $request->mergeIfMissing(['create_user' => true]);
        $validated = $request->validate([
            'name' => 'required|string|min:2|max:255',
            'father_name' => 'required|string|min:2|max:255',
            'contact' => ['required', 'string', 'regex:/^[\d\s\-()+]{8,}$/'],
            'create_user' => 'sometimes|boolean',
            'email' => ['nullable', 'email', 'max:255', Rule::requiredIf($request->boolean('create_user')), 'unique:users,email'],
            'education_lvl' => 'required|string|max:255',
            'position_id' => 'required|max:255',
            'salary' => 'required|numeric|gt:0',
            'bonus' => 'boolean',
            'addr' => 'required|string|min:5',
            'ID_file' => 'nullable|string|max:255',
            'assure_file' => 'nullable|string|max:255',
            'join_date' => 'required|date',
            'contract_duration' => 'required|string|max:255',
        ]);

        $Employee = DB::transaction(function () use ($validated, $request) {
            $employee = Employee::create(collect($validated)->except(['email', 'create_user'])->all());
            if ($request->boolean('create_user', true)) {
                $user = User::create([
                    'name' => $employee->name,
                    'email' => $validated['email'],
                    'password' => Hash::make('1234'),
                    'phone' => $employee->contact,
                    'address' => $employee->addr,
                    'acc_status' => 'active',
                ]);
                $employee->update(['user_id' => $user->id]);
            }
            return $employee;
        });

        return new EmployeeResource($Employee->load(['position', 'user']));
    }

    public function show(Employee $Employee): EmployeeResource
    {
        return new EmployeeResource($Employee->load(['position', 'user']));
    }

    public function update(Request $request, Employee $Employee): EmployeeResource
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|min:2|max:255',
            'father_name' => 'sometimes|required|string|min:2|max:255',
            'contact' => ['sometimes', 'required', 'string', 'regex:/^[\d\s\-()+]{8,}$/'],
            'create_user' => 'sometimes|boolean',
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($Employee->user_id)],
            'education_lvl' => 'sometimes|required|string|max:255',
            'position_id' => 'sometimes|required|max:255',
            'salary' => 'sometimes|required|numeric|gt:0',
            'bonus' => 'boolean',
            'addr' => 'sometimes|required|string|min:5',
            'ID_file' => 'nullable|string|max:255',
            'assure_file' => 'nullable|string|max:255',
            'join_date' => 'sometimes|required|date',
            'contract_duration' => 'sometimes|required|string|max:255',
        ]);

        DB::transaction(function () use ($Employee, $validated, $request) {
            $Employee->update(collect($validated)->except(['email', 'create_user'])->all());
            if (!$Employee->user && $request->boolean('create_user')) {
                $request->validate(['email' => 'required|email|max:255|unique:users,email']);
                $Employee->user()->associate(User::create([
                    'name' => $Employee->name, 'email' => $validated['email'], 'password' => Hash::make('1234'),
                    'phone' => $Employee->contact, 'address' => $Employee->addr, 'acc_status' => 'active',
                ]));
                $Employee->save();
            }
            if ($Employee->user) {
                $Employee->user->update(array_filter([
                    'name' => $validated['name'] ?? null,
                    'email' => $validated['email'] ?? null,
                    'phone' => $validated['contact'] ?? null,
                    'address' => $validated['addr'] ?? null,
                ], fn ($value) => $value !== null));
            }
        });

        return new EmployeeResource($Employee->fresh(['position', 'user']));
    }

    public function destroy(Employee $Employee): JsonResponse
    {
        DB::transaction(function () use ($Employee) {
            $Employee->delete();
            $Employee->user?->update(['acc_status' => 'inactive']);
        });

        return response()->json([
            'message' => 'Employee record successfully deleted.'
        ]);
    }
}
