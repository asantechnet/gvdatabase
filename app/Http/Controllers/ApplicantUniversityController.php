<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\ApplicantUniversity;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * University applications belonging to a visa case. A case routinely
 * spans several universities (often in different countries), so each
 * application tracks its own intake and outcome while
 * applicants.university_id keeps naming the primary choice.
 */
class ApplicantUniversityController extends Controller
{
    public function index(Applicant $applicant)
    {
        return response()->json(['success' => true, 'data' => $applicant->universities()->with('university:id,name,country_id')->latest()->get()]);
    }

    public function store(Request $request, Applicant $applicant)
    {
        $data = $request->validate([
            'university_id' => ['required', 'integer', 'exists:universities,id'],
            'status' => ['sometimes', Rule::in(ApplicantUniversity::STATUSES)],
            'intake' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        abort_if($applicant->universities()->where('university_id', $data['university_id'])->where('intake', $data['intake'] ?? null)->exists(), 422, 'business.applicant_university_duplicate');

        $application = $applicant->universities()->create($data);

        return response()->json(['success' => true, 'data' => $application->load('university:id,name,country_id')], 201);
    }

    public function update(Request $request, Applicant $applicant, ApplicantUniversity $application)
    {
        abort_unless($application->applicant_id === $applicant->id, 404);
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(ApplicantUniversity::STATUSES)],
            'intake' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $application->update($data);

        return response()->json(['success' => true, 'data' => $application->fresh()->load('university:id,name,country_id')]);
    }

    public function destroy(Applicant $applicant, ApplicantUniversity $application)
    {
        abort_unless($application->applicant_id === $applicant->id, 404);
        $application->delete();

        return response()->noContent();
    }
}
