<?php

namespace App\Http\Controllers;

use App\Http\Resources\CustomerServiceResource;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerServiceController extends Controller
{
    public function store(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'services' => ['required', 'array', 'min:1'],
            'services.*.service_id' => ['required', 'integer', 'distinct', 'exists:services,id'],
        ]);

        $records = DB::transaction(function () use ($customer, $validated) {
            return collect($validated['services'])->map(function (array $data) use ($customer) {
                $service = Service::findOrFail($data['service_id']);

                return $customer->customerServices()->create([
                    'service_id' => $data['service_id'],
                    'deadline' => now()->addMonthsNoOverflow($service->duration)->toDateString(),
                ])->load('service.currency');
            });
        });

        return CustomerServiceResource::collection($records);
    }

    public function update(Request $request, Customer $customer, CustomerService $customerService)
    {
        abort_unless($customerService->customer_id === $customer->id, 404);

        $validated = $request->validate([
            'deadline' => ['required', 'date'],
        ]);

        $customerService->update($validated);

        return new CustomerServiceResource($customerService->load('service.currency'));
    }

    public function destroy(Customer $customer, CustomerService $customerService)
    {
        abort_unless($customerService->customer_id === $customer->id, 404);

        $customerService->delete();

        return response()->noContent();
    }
}
