<?php

namespace App\Http\Controllers;

use App\Http\Resources\CustomerResource;
use App\Http\Resources\VisitorResource;
use App\Models\CrmInteraction;
use App\Models\Customer;
use App\Models\Visitor;
use App\Support\CrmMarketerAttribution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
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

        $query = Visitor::query()->with('marketerEmployee')
            ->when(! $request->boolean('include_converted'), fn ($query) => $query->whereNull('converted_at'));

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

        if ($request->integer('marketer_id')) {
            $query->where('marketer_id', $request->integer('marketer_id'));
        } elseif ($marketer) {
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
            'marketer' => 'nullable|string|max:255',
            'marketer_id' => CrmMarketerAttribution::marketerIdRules(),
            'date' => 'required|date',
            'notes' => 'nullable|string|max:10000',
        ]);

        $visitor = Visitor::create(CrmMarketerAttribution::apply($request, $validated, creating: true));

        return new VisitorResource($visitor->load(['marketerEmployee', 'interactions.creator']));
    }

    /**
     * Display the specified resource.
     */
    public function show(Visitor $visitor): VisitorResource
    {
        return new VisitorResource($visitor->load(['marketerEmployee', 'interactions.creator']));
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
            'marketer' => 'sometimes|nullable|string|max:255',
            'marketer_id' => CrmMarketerAttribution::marketerIdRules(),
            'date' => 'sometimes|required|date',
            'notes' => 'nullable|string|max:10000',
        ]);

        $visitor->update(CrmMarketerAttribution::apply($request, $validated, creating: false));

        return new VisitorResource($visitor->load(['marketerEmployee', 'interactions.creator']));
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
            $visitor = Visitor::query()->lockForUpdate()->findOrFail($visitor->id);
            abort_if($visitor->converted_at, 422, 'business.visitor_already_converted');
            $customer = Customer::create([
                'name' => $visitor->name,
                'company' => $visitor->company,
                'contact' => $visitor->contact,
                'email' => $visitor->email,
                'marketer' => $visitor->marketer,
                'marketer_id' => $visitor->marketer_id,
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

            $visitor->update(['converted_at' => now()]);

            return $customer;
        });

        return new CustomerResource($customer->load([
            'customerServices.service.currency',
            'marketerEmployee',
            'interactions.creator',
        ]));
    }
}
