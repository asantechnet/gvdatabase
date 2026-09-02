<?php

namespace App\Http\Controllers;

use App\Models\Visitor;
use App\Models\Customer;
use App\Models\CrmInteraction;
use App\Http\Resources\VisitorResource;
use App\Http\Resources\CustomerResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class VisitorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = $request->input('search');
        $company = $request->input('company');
        $marketer = $request->input('marketer');

        $query = Visitor::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('company', 'like', "%$search%")
                    ->orWhere('contact', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%");
            });
        }

        if ($company) {
            $query->where('company', $company);
        }

        if ($marketer) {
            $query->where('marketer', $marketer);
        }

        return VisitorResource::collection($query->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): VisitorResource
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company' => 'required|string|max:255',
            'contact' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'service' => 'required|string|max:255',
            'marketer' => 'required|string|max:255',
            'date' => 'required|date',
            'notes' => 'nullable|string|max:10000',
        ]);

        $visitor = Visitor::create($validated);

        return new VisitorResource($visitor->load('interactions.creator'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Visitor $visitor): VisitorResource
    {
        return new VisitorResource($visitor->load('interactions.creator'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Visitor $visitor): VisitorResource
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'company' => 'sometimes|required|string|max:255',
            'contact' => 'sometimes|required|string|max:255',
            'email' => 'nullable|email|max:255',
            'service' => 'sometimes|required|string|max:255',
            'marketer' => 'sometimes|required|string|max:255',
            'date' => 'sometimes|required|date',
            'notes' => 'nullable|string|max:10000',
        ]);

        $visitor->update($validated);

        return new VisitorResource($visitor->load('interactions.creator'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Visitor $visitor): JsonResponse
    {
        $visitor->delete();

        return response()->json(null, 204);
    }

    public function convertToCustomer(Visitor $visitor): CustomerResource
    {
        $customer = DB::transaction(function () use ($visitor) {
            $customer = Customer::create([
                'name' => $visitor->name,
                'company' => $visitor->company,
                'contact' => $visitor->contact,
                'email' => $visitor->email,
                'marketer' => $visitor->marketer,
                'notes' => $visitor->notes,
                'date' => now()->toDateString(),
            ]);

            CrmInteraction::query()
                ->where('contactable_type', $visitor->getMorphClass())
                ->where('contactable_id', $visitor->id)
                ->update([
                    'contactable_type' => $customer->getMorphClass(),
                    'contactable_id' => $customer->id,
                ]);

            $visitor->delete();

            return $customer;
        });

        return new CustomerResource($customer->load([
            'customerServices.service.currency',
            'interactions.creator',
        ]));
    }
}
