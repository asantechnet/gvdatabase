<?php

namespace App\Http\Controllers;

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

class VisitorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $dates = $request->validate(['date_from' => ['nullable', 'date_format:Y-m-d'], 'date_to' => ['nullable', 'date_format:Y-m-d', $request->filled('date_from') ? 'after_or_equal:date_from' : 'nullable']]);
        $search = $request->input('search');

        $query = Visitor::query()->with('counselor')
            ->when(! $request->boolean('include_converted'), fn ($query) => $query->whereNull('converted_at'));

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('contact', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%");
            });
        }

        if ($request->integer('counselor_employee_id')) {
            $query->where('counselor_employee_id', $request->integer('counselor_employee_id'));
        }

        $query->when(! empty($dates['date_from']), fn ($q) => $q->whereDate('date', '>=', $dates['date_from']))
            ->when(! empty($dates['date_to']), fn ($q) => $q->whereDate('date', '<=', $dates['date_to']));

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
            'counselor_employee_id' => AgencyStaffAssignment::rules('counselor'),
            'date' => 'required|date',
            'notes' => 'nullable|string|max:10000',
        ]);

        $visitor = Visitor::create($validated);

        return new VisitorResource($visitor->load(['counselor', 'interactions.creator']));
    }

    /**
     * Display the specified resource.
     */
    public function show(Visitor $visitor): VisitorResource
    {
        return new VisitorResource($visitor->load(['counselor', 'interactions.creator']));
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
            'counselor_employee_id' => AgencyStaffAssignment::rules('counselor'),
            'date' => 'sometimes|required|date',
            'notes' => 'nullable|string|max:10000',
        ]);

        $visitor->update($validated);

        return new VisitorResource($visitor->load(['counselor', 'interactions.creator']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Visitor $visitor): JsonResponse
    {
        $visitor->delete();

        return response()->json(null, 204);
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
}
