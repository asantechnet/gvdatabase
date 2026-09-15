<?php

namespace App\Http\Controllers;

use App\Http\Resources\EmployeeResource;
use App\Http\Resources\ServiceResource;
use App\Http\Resources\UserResource;
use App\Models\Employee;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EmployeeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        // Ordering by latest and paginating is standard practice for lists
        return EmployeeResource::collection(Employee::selectable()->with(['position', 'office', 'user', 'commissionServices.currency'])->get());
    }

    /** Active catalogue services available for commission capability setup. */
    public function commissionOptions(): AnonymousResourceCollection
    {
        return ServiceResource::collection(Service::query()
            ->with('currency')
            ->where('status', Service::STATUS_ACTIVE)
            ->orderBy('type')
            ->get());
    }

    /** Employees assigned to the built-in Marketer position for CRM ownership. */
    public function marketerOptions(): AnonymousResourceCollection
    {
        return EmployeeResource::collection(Employee::selectable()
            ->whereHas('position', fn ($query) => $query->where('system_key', 'marketer'))
            ->with(['position', 'office', 'user'])
            ->orderBy('name')
            ->get());
    }

    /** User accounts available for linking from the employee form. */
    public function accountOptions(): AnonymousResourceCollection
    {
        return UserResource::collection(
            User::query()->where('acc_status', 'active')->with(['employee:id,user_id,name', 'marketer:id,user_id,name'])->orderBy('name')->get(),
        );
    }

    public function store(Request $request): EmployeeResource
    {
        $request->mergeIfMissing(['create_user' => true]);
        $validated = $request->validate([
            'name' => 'required|string|min:2|max:255',
            'father_name' => 'required|string|min:2|max:255',
            'contact' => ['required', 'string', 'regex:/^[\d\s\-()+]{8,}$/'],
            'id_number' => ['required', 'string', 'max:100', 'unique:employees,id_number'],
            'bank_account_number' => ['nullable', 'string', 'max:255'],
            'guarantor_id_number' => ['nullable', 'string', 'max:100'],
            'guarantor_phone' => ['nullable', 'string', 'max:30'],
            'guarantor_address' => ['nullable', 'string', 'max:2000'],
            'guarantor_licence' => ['nullable', 'string', 'max:255'],
            'create_user' => 'sometimes|boolean',
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')->where('acc_status', 'active'), Rule::unique('employees', 'user_id')->whereNull('deleted_at')],
            'email' => ['nullable', 'email', 'max:255', Rule::requiredIf($request->boolean('create_user') && ! $request->filled('user_id')), Rule::unique('users', 'email')->ignore($request->input('user_id'))],
            'education_lvl' => 'required|string|max:255',
            'position_id' => ['required', 'integer', Rule::exists('positions', 'id')->whereNull('deleted_at')->where('status', 'active')],
            'office_id' => ['nullable', 'integer', Rule::exists('offices', 'id')->whereNull('deleted_at')],
            'employment_type' => ['required', Rule::in(['salaried', 'commission'])],
            'salary' => ['required', 'numeric', 'min:0'],
            'commissions' => [Rule::requiredIf($request->input('employment_type') === 'commission'), 'array', 'min:1'],
            'commissions.*.service_id' => ['required', 'integer', 'distinct', Rule::exists('services', 'id')->where('status', Service::STATUS_ACTIVE)],
            'commissions.*.percentage' => ['required', 'numeric', 'gt:0', 'lte:100'],
            'addr' => 'required|string|min:5',
            'join_date' => 'required|date',
            // Contract terms are managed in the contract workspace, not the
            // employee profile. Keep a harmless legacy value for the existing
            // non-null database column while new profiles no longer expose it.
            'contract_duration' => 'nullable|string|max:255',
        ]);

        $createdUser = false;
        $Employee = DB::transaction(function () use ($validated, $request, &$createdUser) {
            $employeeData = collect($validated)->except(['email', 'create_user', 'commissions'])->all();
            $employeeData['contract_duration'] = $employeeData['contract_duration'] ?? '';
            $employee = Employee::create($employeeData);
            $this->syncCommissions($employee, $validated['commissions'] ?? []);
            if (! $employee->user_id && $request->boolean('create_user', true)) {
                $user = User::create([
                    'name' => $employee->name,
                    'email' => $validated['email'],
                    'password' => Hash::make(Str::random(64)),
                    'phone' => $employee->contact,
                    'address' => $employee->addr,
                    'acc_status' => 'active',
                    'must_set_password' => true,
                    'roles' => ['staff'],
                ]);
                $employee->update(['user_id' => $user->id]);
                $createdUser = true;
            }

            return $employee;
        });

        if ($createdUser && $Employee->user) {
            $Employee->user->sendEmailVerificationNotification();
        }

        return new EmployeeResource($Employee->load(['position', 'office', 'user', 'commissionServices.currency']));
    }

    public function show(Employee $Employee): EmployeeResource
    {
        return new EmployeeResource($Employee->load(['position', 'office', 'user', 'commissionServices.currency']));
    }

    public function update(Request $request, Employee $Employee): EmployeeResource
    {
        $Employee->loadMissing('user');
        $hadUser = $Employee->user !== null;
        $previousUserId = $Employee->user_id;
        $previousEmail = $Employee->user?->email;

        $targetEmploymentType = $request->input('employment_type', $Employee->employment_type);

        if ($Employee->employment_type === 'commission'
            && $targetEmploymentType !== 'commission'
            && ($Employee->customerServices()->whereNull('deleted_at')->exists()
                || $Employee->deliveryCustomerServices()->whereNull('customer_service.deleted_at')->exists())) {
            throw ValidationException::withMessages([
                'employment_type' => [
                    'Reassign or clear this employee from their customer services before changing them to salaried.',
                ],
            ]);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|min:2|max:255',
            'father_name' => 'sometimes|required|string|min:2|max:255',
            'contact' => ['sometimes', 'required', 'string', 'regex:/^[\d\s\-()+]{8,}$/'],
            'id_number' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('employees', 'id_number')->ignore($Employee->id)],
            'bank_account_number' => ['nullable', 'string', 'max:255'],
            'guarantor_id_number' => ['nullable', 'string', 'max:100'],
            'guarantor_phone' => ['nullable', 'string', 'max:30'],
            'guarantor_address' => ['nullable', 'string', 'max:2000'],
            'guarantor_licence' => ['nullable', 'string', 'max:255'],
            'create_user' => 'sometimes|boolean',
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')->where('acc_status', 'active'), Rule::unique('employees', 'user_id')->whereNull('deleted_at')->ignore($Employee->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($request->input('user_id') ?: $Employee->user_id)],
            'education_lvl' => 'sometimes|required|string|max:255',
            'position_id' => ['sometimes', 'required', 'integer', Rule::exists('positions', 'id')->whereNull('deleted_at')->where('status', 'active')],
            'office_id' => ['nullable', 'integer', Rule::exists('offices', 'id')->whereNull('deleted_at')],
            'employment_type' => ['sometimes', Rule::in(['salaried', 'commission'])],
            'salary' => ['nullable', 'numeric', 'min:0'],
            'commissions' => [Rule::requiredIf($targetEmploymentType === 'commission'), 'array', 'min:1'],
            'commissions.*.service_id' => ['required', 'integer', 'distinct', Rule::exists('services', 'id')->where('status', Service::STATUS_ACTIVE)],
            'commissions.*.percentage' => ['required', 'numeric', 'gt:0', 'lte:100'],
            'addr' => 'sometimes|required|string|min:5',
            'join_date' => 'sometimes|required|date',
            'contract_duration' => 'sometimes|nullable|string|max:255',
        ]);

        $createdUser = false;
        DB::transaction(function () use ($Employee, $validated, $request, $previousUserId, &$createdUser) {
            $Employee = Employee::query()->lockForUpdate()->findOrFail($Employee->id);
            $employeeData = collect($validated)->except(['email', 'create_user', 'commissions'])->all();
            $Employee->update($employeeData);
            if (array_key_exists('commissions', $validated) || array_key_exists('employment_type', $validated)) {
                $this->syncCommissions($Employee, $validated['commissions'] ?? []);
            }
            if (! $Employee->user && $request->boolean('create_user')) {
                $request->validate(['email' => 'required|email|max:255|unique:users,email']);
                $Employee->user()->associate(User::create([
                    'name' => $Employee->name, 'email' => $validated['email'], 'password' => Hash::make(Str::random(64)),
                    'phone' => $Employee->contact, 'address' => $Employee->addr, 'acc_status' => 'active',
                    'must_set_password' => true, 'roles' => ['staff'],
                ]));
                $Employee->save();
                $createdUser = true;
            }
            $userWasReassigned = array_key_exists('user_id', $validated) && (int) $Employee->user_id !== (int) $previousUserId;
            if ($Employee->user && ! $userWasReassigned) {
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

        $Employee = $Employee->fresh(['position', 'office', 'user', 'commissionServices.currency']);

        if ($Employee->user && $createdUser && ! $hadUser) {
            $Employee->user->sendEmailVerificationNotification();
        } elseif ($Employee->user && $previousEmail !== $Employee->user->email) {
            $Employee->user->sendEmailVerificationNotification();
        }

        return new EmployeeResource($Employee);
    }

    public function destroy(Employee $Employee): JsonResponse
    {
        DB::transaction(function () use ($Employee) {
            $Employee = Employee::query()->lockForUpdate()->findOrFail($Employee->id);
            abort_if($Employee->assetAllocations()->exists(), 422, 'business.employee_has_asset_allocations');
            abort_if(
                $Employee->customerServices()->whereIn('status', ['active', 'paused'])->exists()
                || $Employee->deliveryCustomerServices()->whereIn('customer_service.status', ['active', 'paused'])->exists(),
                422,
                'business.employee_has_customer_services',
            );
            abort_if($Employee->user?->tasks()->where('progress', '<', 100)->exists(), 422, 'business.employee_has_open_tasks');

            $Employee->delete();
            $Employee->user?->update(['acc_status' => 'inactive']);
            $Employee->user?->tokens()->delete();
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
