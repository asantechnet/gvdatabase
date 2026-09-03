<?php

namespace App\Http\Controllers;

use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Support\CrmMarketerAttribution;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::with([
            'customerServices.service.currency',
            'customerServices.employee',
            'customerServices.marketer',
            'marketerEmployee',
        ])
            ->when(! $request->boolean('compact'), fn ($query) => $query->with('interactions.creator'));

        if ($search = $request->input('search')) {
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%")
                    ->orWhere('contact', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($company = $request->input('company')) {
            $query->where('company', $company);
        }

        if ($marketerId = $request->integer('marketer_id')) {
            $query->where('marketer_id', $marketerId);
        } elseif ($marketer = $request->input('marketer')) {
            $query->where('marketer', $marketer);
        }

        return CustomerResource::collection($query->get());
    }

    public function store(Request $request)
    {
        $customer = Customer::create(CrmMarketerAttribution::apply(
            $request,
            $this->validateCustomer($request),
            creating: true,
        ));

        return new CustomerResource($customer->load([
            'customerServices.service.currency',
            'customerServices.employee',
            'customerServices.marketer',
            'interactions.creator',
            'marketerEmployee',
        ]));
    }

    public function show(Customer $customer)
    {
        return new CustomerResource($customer->load([
            'customerServices.service.currency',
            'customerServices.employee',
            'customerServices.marketer',
            'interactions.creator',
            'marketerEmployee',
        ]));
    }

    public function update(Request $request, Customer $customer)
    {
        $customer->update(CrmMarketerAttribution::apply(
            $request,
            $this->validateCustomer($request, true),
            creating: false,
        ));

        return new CustomerResource($customer->load([
            'customerServices.service.currency',
            'customerServices.employee',
            'customerServices.marketer',
            'interactions.creator',
            'marketerEmployee',
        ]));
    }

    public function destroy(Customer $customer)
    {
        if ($customer->invoices()->exists()) {
            return response()->json([
                'code' => 'business.customer_has_invoice_history',
            ], 409);
        }

        $customer->delete();

        return response()->noContent();
    }

    private function validateCustomer(Request $request, bool $updating = false): array
    {
        $presence = $updating ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$presence, 'string', 'max:255'],
            'company' => [$presence, 'string', 'max:255'],
            'contact' => [$presence, 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'marketer' => ['nullable', 'string', 'max:255'],
            'marketer_id' => CrmMarketerAttribution::marketerIdRules(),
            'notes' => ['nullable', 'string', 'max:10000'],
            'date' => [$presence, 'date'],
        ]);
    }
}
