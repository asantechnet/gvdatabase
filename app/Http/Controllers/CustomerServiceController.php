<?php

namespace App\Http\Controllers;

use App\Http\Resources\CustomerServiceResource;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomerServiceController extends Controller
{
    public function store(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'services' => ['required', 'array', 'min:1'],
            'services.*.service_id' => ['required', 'integer', 'distinct', 'exists:services,id'],
            'services.*.deadline' => ['nullable', 'date'],
            'services.*.fee' => ['nullable', 'numeric', 'min:0'],
            'services.*.currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'services.*.status' => ['nullable', 'in:active,paused,completed'],
            'services.*.notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $records = DB::transaction(function () use ($customer, $validated) {
            return collect($validated['services'])->map(function (array $data) use ($customer) {
                return $customer->customerServices()->create([
                    'service_id' => $data['service_id'],
                    'deadline' => $data['deadline'] ?? null,
                    'fee' => $data['fee'] ?? null,
                    'currency_id' => $data['currency_id'] ?? null,
                    'status' => $data['status'] ?? 'active',
                    'notes' => $data['notes'] ?? null,
                ])->load('service.currency');
            });
        });

        return CustomerServiceResource::collection($records->load('currency'));
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
            'fee' => ['nullable', 'numeric', 'min:0'],
            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'deadline' => ['nullable', 'date'],
            'status' => ['required', 'in:active,paused,completed'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $customerService->update($validated);

        return new CustomerServiceResource($customerService->load(['service.currency', 'currency']));
    }

    public function destroy(Customer $customer, CustomerService $customerService)
    {
        abort_unless($customerService->customer_id === $customer->id, 404);

        $customerService->delete();

        return response()->noContent();
    }
}
