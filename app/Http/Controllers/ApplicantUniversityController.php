<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\ApplicantUniversity;
use App\Models\University;
use App\Support\EmployeeScope;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * University applications belonging to a visa case. A case routinely
 * spans several universities (often in different countries), so each
 * application tracks its own intake and outcome while
 * scholarship_applicant_details.university_id names the primary choice.
 */
class ApplicantUniversityController extends Controller
{
    public function index(Applicant $applicant)
    {
        $this->assertCaseAccess(request(), $applicant);

        return response()->json(['success' => true, 'data' => $applicant->universities()->with(['university:id,name,country_id', 'semester:id,name,code'])->latest()->get()]);
    }

    public function store(Request $request, Applicant $applicant)
    {
        $this->assertCaseManageAccess($request, $applicant);
        $data = $request->validate([
            'university_id' => ['required', 'integer', 'exists:universities,id'],
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'status' => ['sometimes', Rule::in(ApplicantUniversity::STATUSES)],
            'intake' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        // The case's country scopes which universities apply. A case without
        // a country yet has no context to match against, so any university is
        // acceptable.
        if ($applicant->detail()?->country_id) {
            $universityCountry = University::query()->whereKey($data['university_id'])->value('country_id');
            abort_unless($universityCountry && (int) $universityCountry === (int) $applicant->detail()?->country_id, 422, 'business.university_country_mismatch');
        }
        // Uniqueness follows the schema: one application per case, university
        // and intake (NULL intake matches NULL intake at the application
        // level, mirroring the app-level constraint).
        $duplicateQuery = $applicant->universities()->where('university_id', $data['university_id']);
        if (($data['intake'] ?? null) !== null) {
            $duplicateQuery->where('intake', $data['intake']);
        } else {
            $duplicateQuery->whereNull('intake');
        }
        abort_if($duplicateQuery->exists(), 422, 'business.applicant_university_duplicate');

        $application = $applicant->universities()->create($data);

        return response()->json(['success' => true, 'data' => $application->load(['university:id,name,country_id', 'semester:id,name,code'])], 201);
    }

    public function update(Request $request, Applicant $applicant, ApplicantUniversity $application)
    {
        $this->assertCaseManageAccess($request, $applicant);
        abort_unless($application->applicant_id === $applicant->id, 404);
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(ApplicantUniversity::STATUSES)],
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'intake' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $application->update($data);

        return response()->json(['success' => true, 'data' => $application->fresh()->load(['university:id,name,country_id', 'semester:id,name,code'])]);
    }

    public function destroy(Request $request, Applicant $applicant, ApplicantUniversity $application)
    {
        $this->assertCaseManageAccess($request, $applicant);
        abort_unless($application->applicant_id === $applicant->id, 404);
        $application->delete();

        return response()->noContent();
    }

    private function assertCaseAccess(Request $request, Applicant $applicant): void
    {
        $user = $request->user();
        abort_unless($user, 403, 'auth.forbidden');
        if ($user->isSuperAdmin() || $user->hasPermission('view_all_applicants')) {
            return;
        }

        abort_unless(app(EmployeeScope::class)->canViewOwnedBy($user, $applicant->responsible_employee_id), 403, 'auth.forbidden');
    }

    private function assertCaseManageAccess(Request $request, Applicant $applicant): void
    {
        $user = $request->user();
        abort_unless($user, 403, 'auth.forbidden');
        if ($user->isSuperAdmin() || $user->hasPermission('view_all_applicants')) {
            return;
        }

        abort_unless(app(EmployeeScope::class)->canManageOwnedBy($user, $applicant->responsible_employee_id), 403, 'auth.forbidden');
    }
}
