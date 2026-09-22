<?php

namespace App\Http\Controllers;

use App\Http\Resources\CustomerResource;
use App\Http\Resources\LeadResource;
use App\Models\CrmInteraction;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Visitor;
use App\Support\CrmMarketerAttribution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LeadController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'stage' => ['nullable', Rule::in($this->stages())], 'marketer_id' => ['nullable', 'integer'], 'per_page' => ['nullable', 'integer', 'between:10,100']]);
        $query = Lead::with(['owner', 'marketerEmployee', 'interactions.creator'])->withCount('interactions')->latest();
        $query->when($data['stage'] ?? null, fn ($q, $stage) => $q->where('stage', $stage));
        $query->when($data['marketer_id'] ?? null, fn ($q, $marketerId) => $q->where('marketer_id', $marketerId));
        $query->when($data['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q->where('name', 'like', "%$search%")->orWhere('company', 'like', "%$search%")->orWhere('contact', 'like', "%$search%")->orWhere('email', 'like', "%$search%")));

        return LeadResource::collection($query->paginate($data['per_page'] ?? 25));
    }

    public function store(Request $request): LeadResource
    {
        $data = CrmMarketerAttribution::apply($request, $this->validated($request), creating: true);
        $data['owner_id'] ??= $request->user()->id;
        // Every lead enters the visible New pipeline; no orphaned cards.
        $data['stage'] ??= 'new';
        $stage = $data['stage'] ?? 'new';
        $hasManualProbability = array_key_exists('probability', $data);

        if ($this->isTerminalStage($stage)) {
            $data['probability'] = $this->stageProbability($stage);
            $data['probability_is_manual'] = false;
        } elseif ($hasManualProbability) {
            $data['probability_is_manual'] = true;
        } else {
            $data['probability'] = $this->stageProbability($stage);
            $data['probability_is_manual'] = false;
        }

        return new LeadResource(Lead::create($data)->load(['owner', 'marketerEmployee', 'interactions.creator']));
    }

    public function show(Lead $lead): LeadResource
    {
        return new LeadResource($lead->load(['owner', 'marketerEmployee', 'interactions.creator']));
    }

    public function update(Request $request, Lead $lead): LeadResource
    {
        $data = CrmMarketerAttribution::apply($request, $this->validated($request, true), creating: false);
        $stage = $data['stage'] ?? $lead->stage;
        if (isset($data['stage']) && $data['stage'] !== $lead->stage) {
            $this->assertStageTransition($lead->stage, $stage);
        }

        $hasManualProbability = array_key_exists('probability', $data);
        $manualOverride = array_key_exists('probability_is_manual', $data)
            ? filter_var($data['probability_is_manual'], FILTER_VALIDATE_BOOLEAN)
            : ($hasManualProbability || $lead->probability_is_manual);

        // Won and lost are factual outcomes, so they remain 100% and 0%.
        // Every non-terminal stage respects a user-entered confidence until
        // the user explicitly returns it to the pipeline score.
        if ($this->isTerminalStage($stage)) {
            $data['probability'] = $this->stageProbability($stage);
            $data['probability_is_manual'] = false;
        } elseif ($hasManualProbability) {
            $data['probability_is_manual'] = true;
        } elseif (! $manualOverride) {
            $data['probability'] = $this->stageProbability($stage);
            $data['probability_is_manual'] = false;
        }
        $lead->update($data);

        return new LeadResource($lead->load(['owner', 'marketerEmployee', 'interactions.creator']));
    }

    public function destroy(Lead $lead): JsonResponse
    {
        $lead->delete();

        return response()->json(null, 204);
    }

    public function fromVisitor(Request $request, Visitor $visitor): LeadResource
    {
        $lead = DB::transaction(function () use ($request, $visitor) {
            $visitor = Visitor::query()->lockForUpdate()->findOrFail($visitor->id);
            abort_if($visitor->converted_at, 422, 'business.visitor_already_converted');
            $lead = Lead::firstOrCreate(['visitor_id' => $visitor->id], ['name' => $visitor->name, 'company' => $visitor->company,
                'contact' => $visitor->contact, 'email' => $visitor->email, 'service_interest' => $visitor->service, 'marketer' => $visitor->marketer, 'marketer_id' => $visitor->marketer_id,
                'notes' => $visitor->notes, 'owner_id' => $request->user()->id]);
            CrmInteraction::query()->where('contactable_type', $visitor->getMorphClass())->where('contactable_id', $visitor->id)
                ->update(['contactable_type' => $lead->getMorphClass(), 'contactable_id' => $lead->id]);
            $visitor->update(['converted_at' => now()]);

            return $lead;
        });

        return new LeadResource($lead->load(['owner', 'marketerEmployee', 'interactions.creator']));
    }

    public function convert(Lead $lead): CustomerResource
    {
        $providedEmail = request()->validate([
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
        ])['email'] ?? null;

        $customer = DB::transaction(function () use ($lead, $providedEmail) {
            $lead = Lead::query()->lockForUpdate()->findOrFail($lead->id);
            abort_if($lead->converted_at || $lead->customer_id, 422, 'business.lead_already_converted');
            if ($lead->stage !== 'won') {
                $this->assertStageTransition($lead->stage, 'won');
            }
            $email = trim((string) ($providedEmail ?? $lead->email));
            abort_unless(filter_var($email, FILTER_VALIDATE_EMAIL), 422, 'business.lead_email_required_for_conversion');

            if ($lead->email !== $email) {
                $lead->update(['email' => $email]);
            }

            $customer = Customer::create(['name' => $lead->name, 'company' => $lead->company ?: $lead->name, 'contact' => $lead->contact,
                'email' => $email, 'marketer' => $lead->marketer, 'marketer_id' => $lead->marketer_id, 'notes' => $lead->notes, 'date' => now()->toDateString()]);
            CrmInteraction::query()->where('contactable_type', $lead->getMorphClass())->where('contactable_id', $lead->id)
                ->update(['contactable_type' => $customer->getMorphClass(), 'contactable_id' => $customer->id]);
            $lead->update(['stage' => 'won', 'probability' => 100, 'probability_is_manual' => false, 'customer_id' => $customer->id, 'converted_at' => now()]);

            return $customer;
        });

        return new CustomerResource($customer->load(['customerServices.service.currency', 'marketerEmployee', 'interactions.creator']));
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate(['name' => [$required, 'string', 'max:255'], 'company' => ['nullable', 'string', 'max:255'],
            'contact' => [$required, 'string', 'max:255'], 'email' => ['nullable', 'email', 'max:255'], 'service_interest' => ['nullable', 'string', 'max:255'],
            'marketer' => ['nullable', 'string', 'max:255'], 'marketer_id' => CrmMarketerAttribution::marketerIdRules(), 'stage' => ['sometimes', Rule::in($this->stages())], 'probability' => ['sometimes', 'integer', 'between:0,100'], 'probability_is_manual' => ['sometimes', 'boolean'],
            'estimated_value' => ['nullable', 'numeric', 'min:0'], 'next_follow_up_at' => ['nullable', 'date'], 'notes' => ['nullable', 'string', 'max:10000'], 'owner_id' => ['nullable', 'exists:users,id']]);
    }

    private function stages(): array
    {
        return ['new', 'contacted', 'qualified', 'proposal', 'won', 'lost'];
    }

    /**
     * Keep the visual confidence indicator meaningful even when a lead is
     * advanced through the pipeline without a separately entered percentage.
     */
    private function stageProbability(string $stage): int
    {
        return match ($stage) {
            'new' => 10,
            'contacted' => 25,
            'qualified' => 50,
            'proposal' => 75,
            'won' => 100,
            'lost' => 0,
            default => 10,
        };
    }

    private function isTerminalStage(string $stage): bool
    {
        return in_array($stage, ['won', 'lost'], true);
    }

    private function assertStageTransition(string $from, string $to): void
    {
        $allowed = [
            'new' => ['contacted', 'qualified', 'proposal', 'won', 'lost'],
            'contacted' => ['qualified', 'proposal', 'won', 'lost'],
            'qualified' => ['proposal', 'won', 'lost'],
            'proposal' => ['won', 'lost'],
            'won' => [],
            'lost' => [],
        ];

        abort_unless(in_array($to, $allowed[$from] ?? [], true), 422, 'business.lead_stage_transition_invalid');
    }
}
