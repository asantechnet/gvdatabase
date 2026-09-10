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
                'required', 'integer', 'distinct', 'exists:services,id',
                Rule::unique('customer_service', 'service_id')
                    ->where(fn ($query) => $query->where('customer_id', $customer->id)->whereNull('deleted_at')),
            ],
            'services.*.employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')->where('employment_type', 'commission')],
            'services.*.marketer_id' => CrmMarketerAttribution::marketerIdRules(),
            'services.*.deadline' => ['nullable', 'date'],
            'services.*.fee' => ['nullable', 'numeric', 'min:0'],
            'services.*.currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'services.*.status' => ['nullable', 'in:active,paused,completed'],
            'services.*.notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->assertDeliveryCapabilities($validated['services']);

        $records = DB::transaction(function () use ($customer, $validated) {
            Customer::query()->lockForUpdate()->findOrFail($customer->id);

            return collect($validated['services'])->map(function (array $data) use ($customer) {
                $attributes = [
                    'service_id' => $data['service_id'],
                    'employee_id' => $data['employee_id'] ?? null,
                    // A service normally inherits the customer acquisition
                    // owner. Sending marketer_id explicitly (including null)
                    // lets an authorised user override that attribution.
                    'marketer_id' => array_key_exists('marketer_id', $data)
                        ? $data['marketer_id']
                        : $customer->marketer_id,
                    'deadline' => $data['deadline'] ?? null,
                    'fee' => $data['fee'] ?? null,
                    'currency_id' => $data['currency_id'] ?? null,
                    'status' => $data['status'] ?? 'active',
                    'notes' => $data['notes'] ?? null,
                ];

                $record = CustomerService::withTrashed()
                    ->where('customer_id', $customer->id)
                    ->where('service_id', $data['service_id'])
                    ->lockForUpdate()
                    ->first();

                if ($record?->trashed()) {
                    $record->restore();
                    $record->update($attributes);
                } else {
                    $record = $customer->customerServices()->create($attributes);
                }

                return $record->load(['service.currency', 'currency', 'employee', 'marketer']);
            });
        });

        return CustomerServiceResource::collection($records);
    }

    public function update(Request $request, Customer $customer, CustomerService $customerService)
    {
        abort_unless($customerService->customer_id === $customer->id, 404);

        $validated = $request->validate([
            'service_id' => [
                'sometimes',
                'integer',
                'exists:services,id',
                Rule::unique('customer_service', 'service_id')
                    ->where(fn ($query) => $query->where('customer_id', $customer->id))
                    ->ignore($customerService->id),
            ],
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')->where('employment_type', 'commission')],
            'marketer_id' => CrmMarketerAttribution::marketerIdRules(),
            'fee' => ['nullable', 'numeric', 'min:0'],
            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'deadline' => ['nullable', 'date'],
            'status' => ['required', 'in:active,paused,completed'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->assertDeliveryCapability(
            $validated['employee_id'] ?? $customerService->employee_id,
            (int) ($validated['service_id'] ?? $customerService->service_id),
            'employee_id',
        );

        $customerService->update($validated);

        return new CustomerServiceResource($customerService->load(['service.currency', 'currency', 'employee', 'marketer']));
    }

    public function destroy(Customer $customer, CustomerService $customerService)
    {
        abort_unless($customerService->customer_id === $customer->id, 404);

        $customerService->delete();

        return response()->noContent();
    }

    /** @param array<int, array<string, mixed>> $services */
    private function assertDeliveryCapabilities(array $services): void
    {
        $errors = [];

        foreach ($services as $index => $service) {
            $employeeId = $service['employee_id'] ?? null;
            if ($employeeId === null) {
                continue;
            }

            if (! $this->employeeCanDeliver((int) $employeeId, (int) $service['service_id'])) {
                $errors["services.{$index}.employee_id"] = [
                    'Choose a commission employee who is configured to deliver this service.',
                ];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function assertDeliveryCapability(?int $employeeId, int $serviceId, string $field): void
    {
        if ($employeeId !== null && ! $this->employeeCanDeliver($employeeId, $serviceId)) {
            throw ValidationException::withMessages([
                $field => ['Choose a commission employee who is configured to deliver this service.'],
            ]);
        }
    }

    private function employeeCanDeliver(int $employeeId, int $serviceId): bool
    {
        return Employee::query()
            ->whereKey($employeeId)
            ->where('employment_type', 'commission')
            ->whereHas('commissionServices', fn ($query) => $query->whereKey($serviceId))
            ->exists();
    }
}
