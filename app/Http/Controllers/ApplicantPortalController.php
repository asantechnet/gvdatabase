<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\ApplicantStage;
use App\Models\Role;
use App\Models\User;
use App\Services\EducationalWorkflowNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * A deliberately narrow, student-facing boundary. Operational tasks, notes,
 * finance, internal comments and other applicants are never loaded here.
 */
class ApplicantPortalController extends Controller
{
    public function __construct(private readonly EducationalWorkflowNotificationService $notifications) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->portalData($this->applicantFor($request))]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $applicant = $this->applicantFor($request);
        $user = $request->user();
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'father_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'father_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'address' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'profile' => ['sometimes', 'array'],
            'profile.date_of_birth' => ['nullable', 'date', 'before:today'],
            'profile.passport_number' => ['nullable', 'string', 'max:100'],
            'profile.nationality' => ['nullable', 'string', 'max:100'],
            'profile.education_background' => ['nullable', 'string', 'max:5000'],
            'profile.intended_intake' => ['nullable', 'string', 'max:100'],
            'profile.english_test' => ['nullable', 'string', 'max:255'],
            'profile.preferred_contact_method' => ['nullable', Rule::in(['email', 'phone', 'whatsapp'])],
        ]);

        DB::transaction(function () use ($data, $user, $applicant): void {
            $account = collect($data)->only(['name', 'email', 'phone', 'address'])->all();
            $emailChanged = array_key_exists('email', $account) && $account['email'] !== $user->email;
            $user->fill($account);
            if ($emailChanged) {
                $user->email_verified_at = null;
            }
            $user->save();

            // Guardian contact lives on the applicant row (staff intake collects
            // it), while the grade metric stays staff-managed so a student
            // cannot alter the staff-managed grade used for admission decisions.
            $applicantUpdates = collect($data)->only(['father_name', 'father_phone'])->all();

            if (array_key_exists('profile', $data)) {
                $profile = array_merge($applicant->profile_data ?? [], $data['profile'] ?? []);
                $applicantUpdates['profile_data'] = $profile;
                $applicantUpdates['profile_completed_at'] = collect($profile)->filter(fn ($value) => $value !== null && $value !== '')->isNotEmpty() ? now() : null;
            }

            if ($applicantUpdates !== []) {
                $applicant->update($applicantUpdates);
            }

            if ($emailChanged) {
                $user->sendEmailVerificationNotification();
            }
        });

        return response()->json(['success' => true, 'data' => $this->portalData($this->applicantFor($request))]);
    }

    public function attachments(Request $request): JsonResponse
    {
        $applicant = $this->applicantFor($request);

        return response()->json(['success' => true, 'data' => $applicant->attachments()
            ->latest()
            ->get()
            ->map(fn ($attachment) => $this->attachmentData($attachment))]);
    }

    public function uploadAttachment(Request $request): JsonResponse
    {
        $applicant = $this->applicantFor($request);
        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,txt,doc,docx,xls,xlsx,png,jpg,jpeg,webp'],
            'label' => ['nullable', 'string', 'max:120'],
        ]);
        $file = $data['file'];
        $path = $file->storeAs("applicant-attachments/{$applicant->id}", Str::uuid().'.'.$file->extension(), 'local');
        $attachment = $applicant->attachments()->create([
            'uploaded_by' => $request->user()->id,
            'label' => filled($data['label'] ?? null) ? $data['label'] : $file->getClientOriginalName(),
            'category' => 'applicant_portal',
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        $this->notifications->applicantDocumentUploaded($applicant, $attachment, $request->user()->id);

        return response()->json(['success' => true, 'data' => $this->attachmentData($attachment)], 201);
    }

    public function invite(Request $request, Applicant $applicant): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('invite_applicant_portal'), 403, 'auth.forbidden');
        $this->assertStaffCaseAccess($request, $applicant);
        abort_if($applicant->user_id, 422, 'business.request_rejected');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = DB::transaction(function () use ($data, $applicant): User {
            $lockedApplicant = Applicant::query()->lockForUpdate()->findOrFail($applicant->id);
            abort_if($lockedApplicant->user_id, 422, 'business.request_rejected');
            $role = Role::query()->where('name', 'applicant')->firstOrFail();
            $user = User::create([
                ...$data,
                'password' => Hash::make(Str::random(64)),
                'acc_status' => 'active',
                'must_set_password' => true,
            ]);
            $user->roles()->sync([$role->id]);
            $lockedApplicant->update(['user_id' => $user->id]);

            return $user;
        });

        $user->sendEmailVerificationNotification();

        return response()->json(['success' => true, 'data' => [
            'user_id' => $user->id,
            'email' => $user->email,
            'message' => 'Applicant portal invitation sent.',
        ]], 201);
    }

    private function applicantFor(Request $request): Applicant
    {
        abort_unless(in_array('applicant', $request->user()->roleNames(), true), 403, 'auth.forbidden');

        return Applicant::query()
            ->where('user_id', $request->user()->id)
            ->with([
                'user:id,name,email,phone,address',
                'responsibleEmployee.user:id,email,phone',
                'privateDetail.country:id,name', 'privateDetail.university:id,name', 'privateDetail.educationLevel:id,name',
                'scholarshipDetail.country:id,name', 'scholarshipDetail.university:id,name', 'scholarshipDetail.educationLevel:id,name',
                'testDetail.test:id,name', 'academyDetail.program:id,name', 'academyDetail.time:id,name', 'academyDetail.duration:id,name',
                'currentStage:id,key,name,sort_order,is_terminal',
                'stageHistory' => fn ($history) => $history->select('id', 'applicant_id', 'new_stage_id', 'changed_at')->with('newStage:id,key,name,sort_order'),
                'universities' => fn ($applications) => $applications->select('id', 'applicant_id', 'university_id', 'status', 'intake')->with('university:id,name'),
            ])
            ->firstOrFail();
    }

    private function portalData(Applicant $applicant): array
    {
        $stages = ApplicantStage::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'key', 'name', 'sort_order']);
        $currentPosition = $stages->search(fn (ApplicantStage $stage) => $stage->id === $applicant->current_stage_id);

        return [
            'profile' => [
                'name' => $applicant->user?->name,
                'email' => $applicant->user?->email,
                'phone' => $applicant->user?->phone,
                'address' => $applicant->user?->address,
                'father_name' => $applicant->father_name,
                'father_phone' => $applicant->father_phone,
                ...($applicant->profile_data ?? []),
                'completed_at' => $applicant->profile_completed_at?->toISOString(),
            ],
            'case' => [
                'id' => $applicant->id,
                'status' => $applicant->status,
                'created_at' => $applicant->created_at?->toDateString(),
                'service' => $applicant->service,
                'service_details' => match ($applicant->service) {
                    'test_offer' => ['test' => $applicant->testDetail?->test?->name],
                    'academy' => [
                        'program' => $applicant->academyDetail?->program?->name,
                        'time' => $applicant->academyDetail?->time?->name,
                        'duration' => $applicant->academyDetail?->duration?->name,
                    ],
                    default => [],
                },
                'destination_country' => $applicant->detail()?->country?->name,
                'primary_university' => $applicant->detail()?->university?->name,
                'education_level' => $applicant->detail()?->educationLevel?->name,
                'grade_score' => $applicant->detail()?->grade_score !== null ? (float) $applicant->detail()->grade_score : null,
                // Applicant-facing contact details for the case owner only.
                // Internal employee fields remain outside the portal boundary.
                'advisor' => $applicant->responsibleEmployee ? [
                    'name' => $applicant->responsibleEmployee->name,
                    'phone' => $applicant->responsibleEmployee->user?->phone ?: $applicant->responsibleEmployee->contact,
                    'email' => $applicant->responsibleEmployee->user?->email,
                ] : null,
                'current_stage' => $applicant->currentStage ? [
                    'key' => $applicant->currentStage->key,
                    'name' => $applicant->currentStage->name,
                    'is_terminal' => (bool) $applicant->currentStage->is_terminal,
                ] : null,
                'progress' => ['current' => $currentPosition === false ? null : $currentPosition + 1, 'total' => $stages->count()],
                'timeline' => $applicant->stageHistory->sortBy('changed_at')->values()->map(fn ($entry) => [
                    'key' => $entry->newStage?->key,
                    'name' => $entry->newStage?->name,
                    'reached_at' => $entry->changed_at?->toISOString(),
                ])->values(),
                'university_applications' => $applicant->universities->map(fn ($application) => [
                    'university' => $application->university?->name,
                    'status' => $application->status,
                    'intake' => $application->intake,
                ])->values(),
            ],
        ];
    }

    private function attachmentData($attachment): array
    {
        return [
            'id' => $attachment->id,
            'label' => $attachment->label,
            'name' => $attachment->original_name,
            'mime_type' => $attachment->mime_type,
            'size' => $attachment->size,
            'uploaded_at' => $attachment->created_at?->toISOString(),
        ];
    }

    private function assertStaffCaseAccess(Request $request, Applicant $applicant): void
    {
        $user = $request->user();
        if ($user->isSuperAdmin() || array_intersect($user->roleNames(), ['admin', 'manager']) !== []) {
            return;
        }
        abort_unless($applicant->responsibleEmployee?->user_id === $user->id, 403, 'auth.forbidden');
    }
}
