<?php

namespace App\Http\Controllers;

use App\Http\Resources\CustomerResource;
use App\Http\Resources\LeadResource;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Visitor;
use App\Models\CrmInteraction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LeadController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'stage' => ['nullable', Rule::in($this->stages())], 'per_page' => ['nullable', 'integer', 'between:10,100']]);
        $query = Lead::with(['owner', 'interactions.creator'])->withCount('interactions')->latest();
        $query->when($data['stage'] ?? null, fn ($q, $stage) => $q->where('stage', $stage));
        $query->when($data['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q->where('name', 'like', "%$search%")->orWhere('company', 'like', "%$search%")->orWhere('contact', 'like', "%$search%")->orWhere('email', 'like', "%$search%")));
        return LeadResource::collection($query->paginate($data['per_page'] ?? 25));
    }

    public function store(Request $request): LeadResource
    {
        $data = $this->validated($request);
        $data['owner_id'] ??= $request->user()->id;
        return new LeadResource(Lead::create($data)->load(['owner', 'interactions.creator']));
    }

    public function show(Lead $lead): LeadResource { return new LeadResource($lead->load(['owner', 'interactions.creator'])); }
    public function update(Request $request, Lead $lead): LeadResource { $lead->update($this->validated($request, true)); return new LeadResource($lead->load(['owner', 'interactions.creator'])); }
    public function destroy(Lead $lead): JsonResponse { $lead->delete(); return response()->json(null, 204); }

    public function fromVisitor(Request $request, Visitor $visitor): LeadResource
    {
        $lead = DB::transaction(function () use ($request, $visitor) {
            $lead = Lead::firstOrCreate(['visitor_id' => $visitor->id], ['name' => $visitor->name, 'company' => $visitor->company,
                'contact' => $visitor->contact, 'email' => $visitor->email, 'service_interest' => $visitor->service, 'marketer' => $visitor->marketer,
                'notes' => $visitor->notes, 'owner_id' => $request->user()->id]);
            CrmInteraction::query()->where('contactable_type', $visitor->getMorphClass())->where('contactable_id', $visitor->id)
                ->update(['contactable_type' => $lead->getMorphClass(), 'contactable_id' => $lead->id]);
            $visitor->update(['converted_at' => now()]);
            return $lead;
        });
        return new LeadResource($lead->load(['owner', 'interactions.creator']));
    }

    public function convert(Lead $lead): CustomerResource
    {
        $customer = DB::transaction(function () use ($lead) {
            $customer = Customer::create(['name' => $lead->name, 'company' => $lead->company ?: $lead->name, 'contact' => $lead->contact,
                'email' => $lead->email, 'marketer' => $lead->marketer, 'notes' => $lead->notes, 'date' => now()->toDateString()]);
            CrmInteraction::query()->where('contactable_type', $lead->getMorphClass())->where('contactable_id', $lead->id)
                ->update(['contactable_type' => $customer->getMorphClass(), 'contactable_id' => $customer->id]);
            $lead->update(['stage' => 'won', 'probability' => 100, 'customer_id' => $customer->id, 'converted_at' => now()]);
            return $customer;
        });
        return new CustomerResource($customer->load(['customerServices.service.currency', 'interactions.creator']));
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        return $request->validate(['name' => [$required, 'string', 'max:255'], 'company' => ['nullable', 'string', 'max:255'],
            'contact' => [$required, 'string', 'max:255'], 'email' => ['nullable', 'email', 'max:255'], 'service_interest' => ['nullable', 'string', 'max:255'],
            'marketer' => ['nullable', 'string', 'max:255'], 'stage' => ['sometimes', Rule::in($this->stages())], 'probability' => ['sometimes', 'integer', 'between:0,100'],
            'estimated_value' => ['nullable', 'numeric', 'min:0'], 'next_follow_up_at' => ['nullable', 'date'], 'notes' => ['nullable', 'string', 'max:10000'], 'owner_id' => ['nullable', 'exists:users,id']]);
    }
    private function stages(): array { return ['new', 'contacted', 'qualified', 'proposal', 'won', 'lost']; }
}
