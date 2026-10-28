<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PaginatesLists;
use App\Http\Resources\ApplicantResource;
use App\Http\Resources\VisitorResource;
use App\Models\Applicant;
use App\Models\ApplicantStage;
use App\Models\CrmInteraction;
use App\Models\Visitor;
use App\Support\AgencyStaffAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class VisitorController extends Controller
{
    use PaginatesLists;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $dates = $request->validate(['date_from' => ['nullable', 'date_format:Y-m-d'], 'date_to' => ['nullable', 'date_format:Y-m-d', $request->filled('date_from') ? 'after_or_equal:date_from' : 'nullable']]);
        $request->validate([
            'follow_up' => ['nullable', 'boolean'],
            'consultant_employee_id' => ['nullable', 'integer'],
            'education_level_id' => ['nullable', 'integer'],
            'province_id' => ['nullable', 'integer'],
            'english_level_id' => ['nullable', 'integer'],
            'acquisition_route_id' => ['nullable', 'integer'],
        ]);
        $search = $request->input('search');

        $query = Visitor::query()
            ->with(['consultant', 'educationLevel', 'province', 'englishLevel', 'acquisitionRoute'])
            ->when(! $request->boolean('include_converted'), fn ($query) => $query->whereNull('converted_at'));

        // The visitor surface is split into two lists on one page: the Visitors
        // list (not marked worthy of follow-up) and the Follow-up list (marked).
        // Omitting the filter keeps the endpoint's historical "all visitors"
        // behaviour for other consumers.
        if ($request->has('follow_up')) {
            $query->where('follow_up', $request->boolean('follow_up'));
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('contact', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%");
            });
        }

        foreach (['consultant_employee_id', 'education_level_id', 'province_id', 'english_level_id', 'acquisition_route_id'] as $field) {
            if ($request->integer($field)) {
                $query->where($field, $request->integer($field));
            }
        }

        $query->when(! empty($dates['date_from']), fn ($q) => $q->whereDate('date', '>=', $dates['date_from']))
            ->when(! empty($dates['date_to']), fn ($q) => $q->whereDate('date', '<=', $dates['date_to']));
        // Newest visits first; the id breaks ties between same-day rows.
        $query->orderByDesc('date')->orderByDesc('id');

        if ($this->requestedPerPage($request)) {
            return response()->json(['success' => true] + $this->paginatedPayload($request, $query, fn ($rows) => VisitorResource::collection($rows)->resolve()));
        }

        return VisitorResource::collection($query->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): VisitorResource
    {
        $validated = $request->validate([
            'company' => ['prohibited'],
            'marketer' => ['prohibited'],
            'marketer_id' => ['prohibited'],
            'name' => 'required|string|max:255',
            'contact' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'service' => 'required|string|max:255',
            'consultant_employee_id' => AgencyStaffAssignment::rules('counselor'),
            'education_level_id' => ['nullable', 'integer', Rule::exists('education_levels', 'id')->whereNull('deleted_at')],
            'age' => ['nullable', 'integer', 'min:1', 'max:120'],
            'grade_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'province_id' => ['nullable', 'integer', Rule::exists('provinces', 'id')->whereNull('deleted_at')],
            'english_level_id' => ['nullable', 'integer', Rule::exists('english_levels', 'id')->whereNull('deleted_at')],
            'acquisition_route_id' => ['nullable', 'integer', Rule::exists('acquisition_routes', 'id')->whereNull('deleted_at')],
            'consultant_review' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'date' => 'required|date',
            'notes' => 'nullable|string|max:10000',
        ]);

        // A consultant creating a visitor is that visitor's consultant. The
        // frontend locks the field, and this keeps the rule true even for a
        // direct API call: the signed-in consultant's employee id always wins.
        if ($this->isConsultant($request)) {
            $validated['consultant_employee_id'] = $this->lockedConsultantId($request);
        }

        $this->guardReviewPermission($request, $validated);

        $visitor = Visitor::create($validated);

        return new VisitorResource($visitor->load($this->relations()));
    }

    /**
     * Display the specified resource.
     */
    public function show(Visitor $visitor): VisitorResource
    {
        return new VisitorResource($visitor->load($this->relations()));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Visitor $visitor): VisitorResource
    {
        $validated = $request->validate([
            'company' => ['prohibited'],
            'name' => 'sometimes|required|string|max:255',
            'contact' => 'sometimes|required|string|max:255',
            'email' => 'nullable|email|max:255',
            'service' => 'sometimes|required|string|max:255',
            'consultant_employee_id' => AgencyStaffAssignment::rules('counselor'),
            'education_level_id' => ['nullable', 'integer', Rule::exists('education_levels', 'id')->whereNull('deleted_at')],
            'age' => ['nullable', 'integer', 'min:1', 'max:120'],
            'grade_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'province_id' => ['nullable', 'integer', Rule::exists('provinces', 'id')->whereNull('deleted_at')],
            'english_level_id' => ['nullable', 'integer', Rule::exists('english_levels', 'id')->whereNull('deleted_at')],
            'acquisition_route_id' => ['nullable', 'integer', Rule::exists('acquisition_routes', 'id')->whereNull('deleted_at')],
            'consultant_review' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'date' => 'sometimes|required|date',
            'notes' => 'nullable|string|max:10000',
        ]);

        // The consultant is immutable for consultant-role users: an edit may
        // not move somebody else's visitor onto (or off) their own desk.
        if ($this->isConsultant($request)) {
            unset($validated['consultant_employee_id']);
        }

        $this->guardReviewPermission($request, $validated);

        $visitor->update($validated);

        return new VisitorResource($visitor->load($this->relations()));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Visitor $visitor): JsonResponse
    {
        $visitor->delete();

        return response()->json(null, 204);
    }

    /**
     * Mark or unmark a visitor as worthy of follow-up.
     *
     * One endpoint drives both directions so the two lists stay symmetric: the
     * Visitors list moves a record in, the Follow-up list moves it back out.
     */
    public function setFollowUp(Request $request, Visitor $visitor): VisitorResource
    {
        $validated = $request->validate(['follow_up' => ['required', 'boolean']]);

        $visitor->update(['follow_up' => $validated['follow_up']]);

        return new VisitorResource($visitor->load($this->relations()));
    }

    /** A review is editable by its dedicated permission, independent of visitor editing. */
    public function updateConsultantReview(Request $request, Visitor $visitor): VisitorResource
    {
        $data = $request->validate(['consultant_review' => ['nullable', 'string', 'max:10000']]);
        $visitor->update($data);

        return new VisitorResource($visitor->load($this->relations()));
    }

    public function convertToApplicant(Visitor $visitor): ApplicantResource
    {
        $applicant = DB::transaction(function () use ($visitor) {
            $visitor = Visitor::query()->lockForUpdate()->findOrFail($visitor->id);
            abort_if($visitor->converted_at, 422, 'business.visitor_already_converted');
            $applicant = Applicant::create([
                'name' => $visitor->name,
                'contact' => $visitor->contact,
                'email' => $visitor->email,
                'notes' => $visitor->notes,
                'consultant_review' => $visitor->consultant_review,
                'status' => 'active',
                'current_stage_id' => ApplicantStage::query()->where('key', 'new')->value('id'),
            ]);

            CrmInteraction::query()
                ->where('contactable_type', $visitor->getMorphClass())
                ->where('contactable_id', $visitor->id)
                ->update([
                    'contactable_type' => $applicant->getMorphClass(),
                    'contactable_id' => $applicant->id,
                ]);

            $visitor->update(['converted_at' => now()]);

            return $applicant;
        });

        return new ApplicantResource($applicant->load([
            'applicantContracts.currency',
            'admissionEmployee',
            'interactions.creator',
        ]));
    }

    private function relations(): array
    {
        return ['consultant', 'educationLevel', 'province', 'englishLevel', 'acquisitionRoute', 'interactions.creator'];
    }

    private function isConsultant(Request $request): bool
    {
        return array_intersect($request->user()->roleNames(), ['counselor', 'senior_counselor']) !== [];
    }

    /**
     * The employee id a consultant-role user must be recorded as. A consultant
     * without an employee profile cannot own an enquiry.
     */
    private function lockedConsultantId(Request $request): int
    {
        $employeeId = $request->user()->employee?->id;
        abort_unless($employeeId, 422, 'business.employee_profile_missing');

        return (int) $employeeId;
    }

    /**
     * A consultant review has its own permission; a visitor edit alone does not
     * grant it.
     *
     * @param  array<string, mixed>  $data
     */
    private function guardReviewPermission(Request $request, array $data): void
    {
        if (array_key_exists('consultant_review', $data) && ! $request->user()->hasPermission('review_visitors')) {
            abort(403, 'auth.forbidden');
        }
    }
}
