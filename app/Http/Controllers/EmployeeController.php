<?php

namespace App\Http\Controllers;

use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        // Ordering by latest and paginating is standard practice for lists
        return EmployeeResource::collection(Employee::selectable()->with(['position', 'office', 'user', 'commissionServices'])->get());
    }

    public function store(Request $request): EmployeeResource
    {
        $request->mergeIfMissing(['create_user' => true]);
        $validated = $request->validate([
            'name' => 'required|string|min:2|max:255',
            'father_name' => 'required|string|min:2|max:255',
            'contact' => ['required', 'string', 'regex:/^[\d\s\-()+]{8,}$/'],
            'id_number' => ['required', 'string', 'max:100', 'unique:employees,id_number'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'string', 'max:255'],
            'guarantor_id_number' => ['nullable', 'string', 'max:100'],
            'guarantor_phone' => ['nullable', 'string', 'max:30'],
            'guarantor_address' => ['nullable', 'string', 'max:2000'],
            'guarantor_licence' => ['nullable', 'string', 'max:255'],
            'create_user' => 'sometimes|boolean',
            'email' => ['nullable', 'email', 'max:255', Rule::requiredIf($request->boolean('create_user')), 'unique:users,email'],
            'education_lvl' => 'required|string|max:255',
            'position_id' => 'required|max:255',
            'office_id' => ['nullable', 'integer', Rule::exists('offices', 'id')->whereNull('deleted_at')],
            'employment_type' => ['required', Rule::in(['salaried', 'commission'])],
            'salary' => [Rule::requiredIf($request->input('employment_type', 'salaried') === 'salaried'), 'nullable', 'numeric', 'gt:0'],
            'commissions' => ['nullable', 'array'],
            'commissions.*.service_id' => ['required', 'integer', 'distinct', 'exists:services,id'],
            'commissions.*.percentage' => ['required', 'numeric', 'gt:0', 'lte:100'],
            'addr' => 'required|string|min:5',
            'ID_file' => 'nullable|string|max:255',
            'assure_file' => 'nullable|string|max:255',
            'join_date' => 'required|date',
            'contract_duration' => 'required|string|max:255',
        ]);

        $Employee = DB::transaction(function () use ($validated, $request) {
            $employee = Employee::create(collect($validated)->except(['email', 'create_user', 'commissions'])->all());
            $this->syncCommissions($employee, $validated['commissions'] ?? []);
            if ($request->boolean('create_user', true)) {
                $user = User::create([
                    'name' => $employee->name,
                    'email' => $validated['email'],
                    'password' => Hash::make(Str::random(64)),
                    'phone' => $employee->contact,
                    'address' => $employee->addr,
                    'acc_status' => 'active',
                ]);
                $employee->update(['user_id' => $user->id]);
            }

            return $employee;
        });

        if ($Employee->user) {
            $Employee->user->sendEmailVerificationNotification();
            Password::sendResetLink(['email' => $Employee->user->email]);
        }

        return new EmployeeResource($Employee->load(['position', 'office', 'user', 'commissionServices']));
    }

    public function show(Employee $Employee): EmployeeResource
    {
        return new EmployeeResource($Employee->load(['position', 'office', 'user', 'commissionServices']));
    }

    public function update(Request $request, Employee $Employee): EmployeeResource
    {
        $Employee->loadMissing('user');
        $hadUser = $Employee->user !== null;
        $previousEmail = $Employee->user?->email;

        $validated = $request->validate([
            'name' => 'sometimes|required|string|min:2|max:255',
            'father_name' => 'sometimes|required|string|min:2|max:255',
            'contact' => ['sometimes', 'required', 'string', 'regex:/^[\d\s\-()+]{8,}$/'],
            'id_number' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('employees', 'id_number')->ignore($Employee->id)],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'string', 'max:255'],
            'guarantor_id_number' => ['nullable', 'string', 'max:100'],
            'guarantor_phone' => ['nullable', 'string', 'max:30'],
            'guarantor_address' => ['nullable', 'string', 'max:2000'],
            'guarantor_licence' => ['nullable', 'string', 'max:255'],
            'create_user' => 'sometimes|boolean',
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($Employee->user_id)],
            'education_lvl' => 'sometimes|required|string|max:255',
            'position_id' => 'sometimes|required|max:255',
            'office_id' => ['nullable', 'integer', Rule::exists('offices', 'id')->whereNull('deleted_at')],
            'employment_type' => ['sometimes', Rule::in(['salaried', 'commission'])],
            'salary' => ['nullable', 'numeric', 'gt:0'],
            'commissions' => ['nullable', 'array'],
            'commissions.*.service_id' => ['required', 'integer', 'distinct', 'exists:services,id'],
            'commissions.*.percentage' => ['required', 'numeric', 'gt:0', 'lte:100'],
            'addr' => 'sometimes|required|string|min:5',
            'ID_file' => 'nullable|string|max:255',
            'assure_file' => 'nullable|string|max:255',
            'join_date' => 'sometimes|required|date',
            'contract_duration' => 'sometimes|required|string|max:255',
        ]);

        DB::transaction(function () use ($Employee, $validated, $request) {
            $Employee->update(collect($validated)->except(['email', 'create_user', 'commissions'])->all());
            if (array_key_exists('commissions', $validated)) {
                $this->syncCommissions($Employee, $validated['commissions'] ?? []);
            }
            if (! $Employee->user && $request->boolean('create_user')) {
                $request->validate(['email' => 'required|email|max:255|unique:users,email']);
                $Employee->user()->associate(User::create([
                    'name' => $Employee->name, 'email' => $validated['email'], 'password' => Hash::make(Str::random(64)),
                    'phone' => $Employee->contact, 'address' => $Employee->addr, 'acc_status' => 'active',
                ]));
                $Employee->save();
            }
            if ($Employee->user) {
                $userData = array_filter([
                    'name' => $validated['name'] ?? null,
                    'email' => $validated['email'] ?? null,
                    'phone' => $validated['contact'] ?? null,
                    'address' => $validated['addr'] ?? null,
                ], fn ($value) => $value !== null);

                if (isset($userData['email']) && $userData['email'] !== $Employee->user->email) {
                    $userData['email_verified_at'] = null;
                }

                $Employee->user->update($userData);
            }
        });

        $Employee = $Employee->fresh(['position', 'office', 'user', 'commissionServices']);

        if ($Employee->user && ! $hadUser) {
            $Employee->user->sendEmailVerificationNotification();
            Password::sendResetLink(['email' => $Employee->user->email]);
        } elseif ($Employee->user && $previousEmail !== $Employee->user->email) {
            $Employee->user->sendEmailVerificationNotification();
        }

        return new EmployeeResource($Employee);
    }

    public function destroy(Employee $Employee): JsonResponse
    {
        DB::transaction(function () use ($Employee) {
            $Employee->delete();
            $Employee->user?->update(['acc_status' => 'inactive']);
        });

        return response()->json([
            'message' => 'Employee record successfully deleted.',
        ]);
    }

    private function syncCommissions(Employee $employee, array $commissions): void
    {
        if ($employee->employment_type !== 'commission') {
            $employee->commissionServices()->detach();

            return;
        }

        $employee->commissionServices()->sync(collect($commissions)->mapWithKeys(
            fn (array $row) => [(int) $row['service_id'] => ['percentage' => $row['percentage']]],
        )->all());
    }
}
