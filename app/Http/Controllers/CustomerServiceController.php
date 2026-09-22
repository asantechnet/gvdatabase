<?php

namespace App\Http\Controllers;

use App\Http\Resources\CustomerServiceResource;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Employee;
use App\Support\CrmMarketerAttribution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomerServiceController extends Controller
{
    public function store(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'services' => ['required', 'array', 'min:1'],
            'services.*.service_id' => [
                'required', 'integer', 'exists:services,id',
            ],
            'services.*.applicant_id' => ['nullable', 'integer', Rule::exists('applicants', 'id')->where('customer_id', $customer->id)],
            'services.*.sold_by_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'services.*.employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')->where('employment_type', 'commission')],
            'services.*.employee_ids' => ['sometimes', 'array'],
            'services.*.employee_ids.*' => ['integer', 'distinct', Rule::exists('employees', 'id')->whereNull('deleted_at')->where('employment_type', 'commission')],
            'services.*.marketer_id' => CrmMarketerAttribution::marketerIdRules(),
            'services.*.outsourcing_company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'services.*.outsourcing_percentage' => ['nullable', 'numeric', 'gt:0', 'lte:100', 'required_with:services.*.outsourcing_company_id'],
            'services.*.deadline' => ['nullable', 'date'],
            'services.*.fee' => ['nullable', 'numeric', 'min:0'],
            'services.*.currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'services.*.status' => ['nullable', 'in:active,paused,completed,cancelled'],
            'services.*.sold_at' => ['nullable', 'date'],
            'services.*.country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'services.*.education_level_id' => ['nullable', 'integer', 'exists:education_levels,id'],
            'services.*.language_id' => ['nullable', 'integer', 'exists:languages,id'],
            'services.*.notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->assertDeliveryCapabilities($validated['services']);
        $this->assertOutsourcingTerms($validated['services']);

        $records = DB::transaction(function () use ($customer, $validated) {
            Customer::query()->lockForUpdate()->findOrFail($customer->id);

            return collect($validated['services'])->map(function (array $data) use ($customer) {
                $employeeIds = $this->deliveryEmployeeIds($data);
                $attributes = [
                    'service_id' => $data['service_id'],
                    'applicant_id' => $data['applicant_id'] ?? null,
                    'sold_by_employee_id' => $data['sold_by_employee_id'] ?? null,
                    // Kept for older API consumers. The relation below is the
                    // authoritative list and may contain more than one person.
                    'employee_id' => $employeeIds[0] ?? null,
                    // A service normally inherits the customer acquisition
                    // owner. Sending marketer_id explicitly (including null)
                    // lets an authorised user override that attribution.
                    'marketer_id' => array_key_exists('marketer_id', $data)
                        ? $data['marketer_id']
                        : $customer->marketer_id,
                    'outsourcing_company_id' => $data['outsourcing_company_id'] ?? null,
                    'outsourcing_percentage' => $data['outsourcing_percentage'] ?? null,
                    'deadline' => $data['deadline'] ?? null,
                    'fee' => $data['fee'] ?? null,
                    'currency_id' => $data['currency_id'] ?? null,
                    'status' => $data['status'] ?? 'active',
                    'notes' => $data['notes'] ?? null,
                    'sold_at' => $data['sold_at'] ?? now()->toDateString(),
                    'country_id' => $data['country_id'] ?? null,
                    'education_level_id' => $data['education_level_id'] ?? null,
                    'language_id' => $data['language_id'] ?? null,
                ];
                $record = $customer->customerServices()->create($attributes);

                $this->syncDeliveryEmployees($record, $employeeIds);

                return $record->load(['service.currency', 'currency', 'employee', 'deliveryEmployees', 'marketer', 'outsourcingCompany', 'applicant', 'customerPackage.package', 'country', 'educationLevel', 'language', 'bonusSource']);
            });
        });

        return CustomerServiceResource::collection($records);
    }

    public function update(Request $request, Customer $customer, CustomerService $customerService)
    {
        abort_unless($customerService->customer_id === $customer->id, 404);
        abort_if($customerService->customer_package_id, 422, 'business.package_service_managed_as_bundle');

        $validated = $request->validate([
            'service_id' => [
                'sometimes',
                'integer',
                'exists:services,id',
            ],
            'applicant_id' => ['nullable', 'integer', Rule::exists('applicants', 'id')->where('customer_id', $customer->id)],
            'sold_by_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')->where('employment_type', 'commission')],
            'employee_ids' => ['sometimes', 'array'],
            'employee_ids.*' => ['integer', 'distinct', Rule::exists('employees', 'id')->whereNull('deleted_at')->where('employment_type', 'commission')],
            'marketer_id' => CrmMarketerAttribution::marketerIdRules(),
            'outsourcing_company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'outsourcing_percentage' => ['nullable', 'numeric', 'gt:0', 'lte:100', 'required_with:outsourcing_company_id'],
            'fee' => ['nullable', 'numeric', 'min:0'],
            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'deadline' => ['nullable', 'date'],
            'status' => ['required', 'in:active,paused,completed,cancelled'],
            'sold_at' => ['nullable', 'date'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'education_level_id' => ['nullable', 'integer', 'exists:education_levels,id'],
            'language_id' => ['nullable', 'integer', 'exists:languages,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (array_key_exists('outsourcing_company_id', $validated) && $validated['outsourcing_company_id'] === null) {
            $validated['outsourcing_percentage'] = null;
        }
        $outsourcingTerms = [
            'outsourcing_company_id' => array_key_exists('outsourcing_company_id', $validated) ? $validated['outsourcing_company_id'] : $customerService->outsourcing_company_id,
            'outsourcing_percentage' => array_key_exists('outsourcing_percentage', $validated) ? $validated['outsourcing_percentage'] : $customerService->outsourcing_percentage,
        ];
        $employeeIds = $this->deliveryEmployeeIds($validated, $customerService);
        $this->assertOutsourcingTerms([$outsourcingTerms]);
        $serviceId = (int) ($validated['service_id'] ?? $customerService->service_id);
        $this->assertDeliveryEmployeeIds($employeeIds, $serviceId, 'employee_ids');
        $validated['employee_id'] = $employeeIds[0] ?? null;
        unset($validated['employee_ids']);

        $customerService->update($validated);
        $this->syncDeliveryEmployees($customerService, $employeeIds);

        return new CustomerServiceResource($customerService->load(['service.currency', 'currency', 'employee', 'deliveryEmployees', 'marketer', 'outsourcingCompany', 'applicant', 'customerPackage.package', 'country', 'educationLevel', 'language', 'bonusSource']));
    }

    public function destroy(Customer $customer, CustomerService $customerService)
    {
        abort_unless($customerService->customer_id === $customer->id, 404);
        abort_if($customerService->customer_package_id, 422, 'business.package_service_managed_as_bundle');

        $customerService->delete();

        return response()->noContent();
    }

    /** @param array<int, array<string, mixed>> $services */
    private function assertDeliveryCapabilities(array $services): void
    {
        $errors = [];

        foreach ($services as $index => $service) {
            foreach ($this->deliveryEmployeeIds($service) as $employeeId) {
                if (! $this->employeeCanDeliver($employeeId, (int) $service['service_id'])) {
                    $field = array_key_exists('employee_ids', $service) ? 'employee_ids' : 'employee_id';
                    $errors["services.{$index}.{$field}"] = [
                        'Choose commission employees who are configured to deliver this service.',
                    ];
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @param array<int, int> $employeeIds */
    private function assertDeliveryEmployeeIds(array $employeeIds, int $serviceId, string $field): void
    {
        foreach ($employeeIds as $employeeId) {
            if ($this->employeeCanDeliver($employeeId, $serviceId)) {
                continue;
            }

            throw ValidationException::withMessages([
                $field => ['Choose a commission employee who is configured to deliver this service.'],
            ]);
        }
    }

    /** @param array<string, mixed> $data @return array<int, int> */
    private function deliveryEmployeeIds(array $data, ?CustomerService $existing = null): array
    {
        if (array_key_exists('employee_ids', $data)) {
            return collect($data['employee_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all();
        }

        if (array_key_exists('employee_id', $data)) {
            return $data['employee_id'] === null ? [] : [(int) $data['employee_id']];
        }

        if (! $existing) {
            return [];
        }

        $employeeIds = $existing->deliveryEmployees()->pluck('employees.id')->all();

        return $employeeIds !== []
            ? $employeeIds
            : ($existing->employee_id ? [(int) $existing->employee_id] : []);
    }

    /** @param array<int, int> $employeeIds */
    private function syncDeliveryEmployees(CustomerService $customerService, array $employeeIds): void
    {
        $percentages = Employee::query()->with(['commissionServices' => fn ($query) => $query->whereKey($customerService->service_id)])
            ->whereIn('id', $employeeIds)
            ->get()
            ->mapWithKeys(fn (Employee $employee) => [
                $employee->id => ['percentage' => $employee->commissionServices->sole()->pivot->percentage],
            ])->all();

        $customerService->deliveryEmployees()->sync($percentages);
    }

    private function employeeCanDeliver(int $employeeId, int $serviceId): bool
    {
        return Employee::query()
            ->whereKey($employeeId)
            ->where('employment_type', 'commission')
            ->whereHas('commissionServices', fn ($query) => $query->whereKey($serviceId))
            ->exists();
    }

    /** @param array<int, array<string, mixed>> $services */
    private function assertOutsourcingTerms(array $services): void
    {
        foreach ($services as $index => $service) {
            $hasCompany = ! empty($service['outsourcing_company_id']);
            $hasRate = array_key_exists('outsourcing_percentage', $service) && $service['outsourcing_percentage'] !== null;
            if ($hasCompany === $hasRate) {
                continue;
            }
            throw ValidationException::withMessages([
                "services.{$index}.outsourcing_company_id" => ['Select both an outsourcing company and its agreed percentage, or leave both blank.'],
            ]);
        }
    }
}
