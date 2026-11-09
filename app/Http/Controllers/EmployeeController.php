<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PaginatesLists;
use App\Http\Resources\EmployeeResource;
use App\Http\Resources\UserResource;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Support\AgencyStaffAssignment;
use App\Support\BuiltInService;
use App\Support\EmployeeScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EmployeeController extends Controller
{
    use PaginatesLists;

    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        // Employee directories are active-only by default; inactive employees
        // are only sent when a management surface explicitly opts in.
        $status = $request->validate([
            'status' => ['sometimes', Rule::in([Employee::STATUS_ACTIVE, Employee::STATUS_INACTIVE, 'all'])],
        ])['status'] ?? Employee::STATUS_ACTIVE;

        // Newest staff first by default; pickers can ask for name order.
        $order = $request->validate([
            'sort' => ['nullable', Rule::in(['created_at', 'name', 'id'])],
            'dir' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
        ]);

        $query = Employee::selectable()
            ->excludePrimaryRecord()
            ->when($status !== 'all', fn ($query) => $query->where('status', $status));
        if (! $request->user()->hasPermission('view_all_employees')) {
            $query->whereIn('id', app(EmployeeScope::class)->visibleEmployeeIds($request->user()));
        }

        // The directory searches the whole staff record, not only the visible
        // page, so the filter runs in the database.
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('father_name', 'like', "%{$search}%")
                ->orWhere('contact', 'like', "%{$search}%")
                ->orWhere('id_number', 'like', "%{$search}%")
                // The staff email is the linked account's email.
                ->orWhereHas('user', fn ($user) => $user->where('email', 'like', "%{$search}%"))
                ->orWhereHas('position', fn ($position) => $position->where('title', 'like', "%{$search}%")));
        }

        if (! empty($filters['position'])) {
            $query->whereHas('position', fn ($position) => $position->where('title', $filters['position']));
        }

        $query->orderBy($order['sort'] ?? 'created_at', $order['dir'] ?? 'desc')->orderBy('id', 'desc');
        $query->with(['position', 'office', 'user', 'departments', 'serviceCommissions']);

        if ($this->requestedPerPage($request)) {
            return response()->json(['success' => true] + $this->paginatedPayload($request, $query, fn ($rows) => EmployeeResource::collection($rows)->resolve()));
        }

        return EmployeeResource::collection($query->get());
    }

    public function assignmentOptions(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate(['specialty' => ['required', Rule::in(['counselor', 'admission'])]]);

        return EmployeeResource::collection(AgencyStaffAssignment::query($data['specialty'])
            ->with(['position', 'office', 'user'])->orderBy('name')->get());
    }

    /** User accounts available for linking from the employee form. */
    public function accountOptions(Request $request): AnonymousResourceCollection
    {
        $query = User::query()->excludePrimaryRecord()->where('acc_status', 'active')->with(['employee:id,user_id,name']);
        if (! $request->user()->hasPermission('view_all_employees')) {
            $query->whereIn('id', app(EmployeeScope::class)->visibleUserIds($request->user()));
        }

        return UserResource::collection($query->orderBy('name')->get());
    }

    public function store(Request $request): EmployeeResource
    {
        $request->mergeIfMissing(['create_user' => true]);
        $validated = $request->validate([
            'name' => 'required|string|min:2|max:255',
            'father_name' => 'required|string|min:2|max:255',
            'contact' => ['required', 'string', 'regex:/^[\d\s\-()+]{8,}$/'],
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'id_number' => ['required', 'string', 'max:100', 'unique:employees,id_number'],
            'bank_account_number' => ['nullable', 'string', 'max:255'],
            'guarantor_name' => ['nullable', 'string', 'max:255'],
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
            'department_ids' => ['sometimes', 'array'],
            'department_ids.*' => ['integer', 'distinct', Rule::exists('departments', 'id')->whereNull('deleted_at')],
            'status' => ['sometimes', Rule::in([Employee::STATUS_ACTIVE, Employee::STATUS_INACTIVE])],
            'salary' => ['required', 'numeric', 'min:0'],
            'addr' => 'required|string|min:5',
            'join_date' => 'required|date',
            // Contract terms are managed in the contract workspace, not the
            // employee profile. Keep a harmless legacy value for the existing
            // non-null database column while new profiles no longer expose it.
            'contract_duration' => 'nullable|string|max:255',
            'off_day_allowance_days' => 'nullable|numeric|min:0|max:366',
            'is_commission_based' => 'sometimes|boolean',
            'service_commissions' => 'nullable|array',
            'service_commissions.*.service' => ['required', Rule::in(BuiltInService::enabled())],
            'service_commissions.*.percentage' => 'required|numeric|min:0|max:100',
        ]);
        $this->guardDepartmentFields($request, $validated);

        // The company-wide allowance is only a catalogue value: it prefills a
        // new employee profile and is never read for an existing employee.
        if (! array_key_exists('off_day_allowance_days', $validated) || $validated['off_day_allowance_days'] === null) {
            $validated['off_day_allowance_days'] = (float) (\App\Models\Setting::latest('id')->value('off_day_allowance_days_per_year') ?? 0);
        }

        $createdUser = false;
        $Employee = DB::transaction(function () use ($validated, $request, &$createdUser) {
            $employeeData = collect($validated)->except(['email', 'create_user', 'department_ids'])->all();
            $employeeData['contract_duration'] = $employeeData['contract_duration'] ?? '';
            $employee = Employee::create($employeeData);
            $this->syncEmployeeDepartments($employee, $validated);
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

            $this->syncServiceCommissions($employee, $request);

            return $employee;
        });

        if ($createdUser && $Employee->user) {
            $Employee->user->sendEmailVerificationNotification();
        }

        return new EmployeeResource($Employee->load(['position', 'office', 'user', 'departments', 'serviceCommissions']));
    }

    public function show(Employee $Employee): EmployeeResource
    {
        $this->authorizeEmployeeView(request(), $Employee);
        abort_if((int) $Employee->id === Employee::primaryRecordId(), 404);

        return new EmployeeResource($Employee->load(['position', 'office', 'user', 'departments', 'serviceCommissions']));
    }

    public function update(Request $request, Employee $Employee): EmployeeResource
    {
        $this->authorizeEmployeeManage($request, $Employee);

        // An employee may update their own profile only when the dedicated
        // permission is granted, and then only their personal details. Pay,
        // position, office, hierarchy, status, join date, allowance and
        // commissions remain HR management fields.
        if (! $request->user()->hasPermission('edit_employees')) {
            return $this->updateOwnProfile($request, $Employee);
        }

        $Employee->loadMissing('user');
        $hadUser = $Employee->user !== null;
        $previousUserId = $Employee->user_id;
        $previousEmail = $Employee->user?->email;

        $validated = $request->validate([
            'name' => 'sometimes|required|string|min:2|max:255',
            'father_name' => 'sometimes|required|string|min:2|max:255',
            'contact' => ['sometimes', 'required', 'string', 'regex:/^[\d\s\-()+]{8,}$/'],
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'id_number' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('employees', 'id_number')->ignore($Employee->id)],
            'bank_account_number' => ['nullable', 'string', 'max:255'],
            'guarantor_name' => ['nullable', 'string', 'max:255'],
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
            'department_ids' => ['sometimes', 'array'],
            'department_ids.*' => ['integer', 'distinct', Rule::exists('departments', 'id')->whereNull('deleted_at')],
            'status' => ['sometimes', Rule::in([Employee::STATUS_ACTIVE, Employee::STATUS_INACTIVE])],
            'salary' => ['nullable', 'numeric', 'min:0'],
            'addr' => 'sometimes|required|string|min:5',
            'join_date' => 'sometimes|required|date',
            'contract_duration' => 'sometimes|nullable|string|max:255',
            'off_day_allowance_days' => 'sometimes|nullable|numeric|min:0|max:366',
            'is_commission_based' => 'sometimes|boolean',
            'service_commissions' => 'sometimes|nullable|array',
            'service_commissions.*.service' => ['required', Rule::in(BuiltInService::enabled())],
            'service_commissions.*.percentage' => 'required|numeric|min:0|max:100',
        ]);
        $this->guardDepartmentFields($request, $validated);

        $createdUser = false;
        DB::transaction(function () use ($Employee, $validated, $request, $previousUserId, &$createdUser) {
            $Employee = Employee::query()->lockForUpdate()->findOrFail($Employee->id);
            $employeeData = collect($validated)->except(['email', 'create_user', 'department_ids'])->all();
            $Employee->update($employeeData);
            $this->syncEmployeeDepartments($Employee, $validated);
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
            $this->syncServiceCommissions($Employee, $request);
        });

        $Employee = $Employee->fresh(['position', 'office', 'user', 'departments', 'serviceCommissions']);

        if ($Employee->user && $createdUser && ! $hadUser) {
            $Employee->user->sendEmailVerificationNotification();
        } elseif ($Employee->user && $previousEmail !== $Employee->user->email) {
            $Employee->user->sendEmailVerificationNotification();
        }

        return new EmployeeResource($Employee);
    }

    /**
     * A self-editor updates only personal, contact, guarantor and bank fields
     * on their own employee row. Everything else is rejected rather than
     * silently ignored, so the UI cannot imply a change that did not happen.
     */
    private function updateOwnProfile(Request $request, Employee $Employee): EmployeeResource
    {
        abort_unless((int) $request->user()->employee?->id === (int) $Employee->id, 403, 'auth.forbidden');

        $allowed = [
            'name', 'father_name', 'contact', 'emergency_contact_name', 'emergency_contact_phone',
            'addr', 'bank_account_number', 'guarantor_name', 'guarantor_id_number',
            'guarantor_phone', 'guarantor_address', 'guarantor_licence',
        ];
        $rejected = array_diff(array_keys($request->all()), $allowed);
        abort_if($rejected !== [], 403, 'auth.forbidden');

        $validated = $request->validate([
            'name' => 'sometimes|required|string|min:2|max:255',
            'father_name' => 'sometimes|required|string|min:2|max:255',
            'contact' => ['sometimes', 'required', 'string', 'regex:/^[\\d\\s\\-()+]{8,}$/'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'addr' => 'sometimes|required|string|min:5',
            'bank_account_number' => ['nullable', 'string', 'max:255'],
            'guarantor_name' => ['nullable', 'string', 'max:255'],
            'guarantor_id_number' => ['nullable', 'string', 'max:100'],
            'guarantor_phone' => ['nullable', 'string', 'max:30'],
            'guarantor_address' => ['nullable', 'string', 'max:2000'],
            'guarantor_licence' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($Employee, $validated): void {
            $Employee = Employee::query()->lockForUpdate()->findOrFail($Employee->id);
            $Employee->update($validated);
            $Employee->user?->update(array_filter([
                'name' => $validated['name'] ?? null,
                'phone' => $validated['contact'] ?? null,
                'address' => $validated['addr'] ?? null,
            ], fn ($value) => $value !== null));
        });

        return new EmployeeResource($Employee->fresh(['position', 'office', 'user', 'departments', 'serviceCommissions']));
    }

    public function destroy(Employee $Employee): JsonResponse
    {
        $this->authorizeEmployeeManage(request(), $Employee);
        DB::transaction(function () use ($Employee) {
            $Employee = Employee::query()->lockForUpdate()->findOrFail($Employee->id);
            abort_if($Employee->assetAllocations()->exists(), 422, 'business.employee_has_asset_allocations');
            abort_if(
                $Employee->soldApplicantContracts()->whereHas('contract', fn ($contracts) => $contracts->whereIn('status', ['active']))->exists(),
                422,
                'business.employee_has_customer_services',
            );
            abort_if($Employee->user?->tasks()->where('progress', '<', 100)->exists(), 422, 'business.employee_has_open_tasks');

            if ($Employee->document_path) {
                Storage::disk('local')->delete($Employee->document_path);
            }
            $Employee->delete();
            $Employee->user?->update(['acc_status' => 'inactive']);
            $Employee->user?->tokens()->delete();
        });

        return response()->json([
            'message' => 'Employee record successfully deleted.',
        ]);
    }

    public function uploadDocument(Request $request, Employee $employee): EmployeeResource
    {
        $this->authorizeEmployeeManage($request, $employee);
        $request->validate(['document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx', 'max:5120']]);
        $file = $request->file('document');
        $path = $file->storeAs("employee-documents/{$employee->id}", Str::uuid().'.'.$file->extension(), 'local');
        if ($employee->document_path) {
            Storage::disk('local')->delete($employee->document_path);
        }
        $employee->update(['document_path' => $path, 'document_name' => $file->getClientOriginalName(), 'document_mime' => $file->getMimeType(), 'document_size' => $file->getSize()]);

        return new EmployeeResource($employee->fresh(['position', 'office', 'user']));
    }

    public function downloadDocument(Employee $employee)
    {
        $this->authorizeEmployeeView(request(), $employee);
        abort_unless($employee->document_path && Storage::disk('local')->exists($employee->document_path), 404);

        return Storage::disk('local')->download($employee->document_path, $employee->document_name ?: 'employee-document');
    }

    public function removeDocument(Employee $employee): EmployeeResource
    {
        $this->authorizeEmployeeManage(request(), $employee);
        if ($employee->document_path) {
            Storage::disk('local')->delete($employee->document_path);
        }
        $employee->update(['document_path' => null, 'document_name' => null, 'document_mime' => null, 'document_size' => null]);

        return new EmployeeResource($employee->fresh(['position', 'office', 'user']));
    }

    /**
     * Only a user with `manage_departments` may change an employee's department
     * memberships. Everyone else can edit the rest of the profile without
     * silently reshuffling the hierarchy.
     *
     * @param  array<string, mixed>  $data
     */
    private function guardDepartmentFields(Request $request, array &$data): void
    {
        if (! array_key_exists('department_ids', $data)) {
            return;
        }

        if (! $request->user()->hasPermission('manage_departments')) {
            unset($data['department_ids']);
        }
    }

    /**
     * Write the submitted membership set through the model's single writer.
     * Omitting `department_ids` leaves the existing set alone; an explicit
     * array (even an empty one) is the employee's complete set.
     */
    private function syncEmployeeDepartments(Employee $employee, array $data): void
    {
        if (! array_key_exists('department_ids', $data)) {
            return;
        }

        $employee->syncDepartments($data['department_ids']);
    }

    /**
     * Replace the employee's per-service commission rates with the submitted
     * list. Only touched when the form actually sent the list, so unrelated
     * partial updates never wipe the rates.
     */
    private function syncServiceCommissions(Employee $employee, Request $request): void
    {
        if (! $request->has('service_commissions')) {
            return;
        }

        $rates = collect($request->input('service_commissions') ?? [])
            ->filter(fn ($row) => is_array($row) && isset($row['service']))
            ->mapWithKeys(fn ($row) => [$row['service'] => (float) ($row['percentage'] ?? 0)])
            ->all();

        // Rates are configuration rows with a unique (employee, service) key;
        // a soft-deleted row would block re-inserting the same pair.
        $employee->serviceCommissions()->forceDelete();
        foreach ($rates as $service => $percentage) {
            $employee->serviceCommissions()->create([
                'service' => $service,
                'percentage' => $percentage,
            ]);
        }
    }

    private function authorizeEmployeeView(Request $request, Employee $employee): void
    {
        if ($request->user()->hasPermission('view_all_employees')) {
            return;
        }

        abort_unless(app(EmployeeScope::class)->canViewOwnedBy($request->user(), $employee->id), 403, 'auth.forbidden');
    }

    private function authorizeEmployeeManage(Request $request, Employee $employee): void
    {
        if ($request->user()->hasPermission('manage_all_employees')) {
            return;
        }

        abort_unless(app(EmployeeScope::class)->canManageOwnedBy($request->user(), $employee->id), 403, 'auth.forbidden');
    }
}
