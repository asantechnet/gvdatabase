<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAcademyEnrollmentRequest;
use App\Http\Resources\AcademyEnrollmentResource;
use App\Models\AcademyEnrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcademyEnrollmentController extends Controller
{
    private const RELATIONS = [
        'student', 'applicant', 'contract', 'line.currency', 'duration',
        'academyClass.program', 'academyClass.time', 'academyClass.teachers',
    ];

    private function withRelations($query)
    {
        return $query->with(self::RELATIONS)->with(['academyClass' => fn ($class) => $class
            ->with(['program', 'time', 'duration', 'teachers'])
            ->withCount(['enrollments as seats_taken' => fn ($enrollments) => $enrollments->where('status', 'active')])]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = $this->withRelations(AcademyEnrollment::query())
            ->when($request->filled('class_id'), fn ($query) => $query->where('class_id', $request->integer('class_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->boolean('ending'), fn ($query) => $query
                ->where('status', 'active')
                ->whereBetween('ends_on', [now()->toDateString(), now()->addDays(30)->toDateString()]))
            ->when($request->filled('teacher_id'), fn ($query) => $query->whereHas(
                'academyClass.teachers',
                fn ($teacher) => $teacher->where('teachers.id', $request->integer('teacher_id')),
            ))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(fn ($query) => $query
                    ->whereHas('student', fn ($student) => $student
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('student_number', 'like', "%{$search}%"))
                    ->orWhereHas('applicant', fn ($applicant) => $applicant
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('contact', 'like', "%{$search}%"))
                    ->orWhereHas('academyClass', fn ($class) => $class->where('name', 'like', "%{$search}%")));
            })
            ->orderByDesc('started_on')->orderByDesc('id');

        $perPage = min(max((int) $request->integer('per_page', 25), 1), 100);
        $page = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => AcademyEnrollmentResource::collection($page->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(Request $request, AcademyEnrollment $academyEnrollment): JsonResponse
    {
        $academyEnrollment->load(self::RELATIONS);

        return response()->json([
            'success' => true,
            'data' => (new AcademyEnrollmentResource($academyEnrollment))->resolve($request),
        ]);
    }

    public function update(UpdateAcademyEnrollmentRequest $request, AcademyEnrollment $academyEnrollment): JsonResponse
    {
        if (in_array($academyEnrollment->status, AcademyEnrollment::TERMINAL_STATUSES, true)) {
            abort(422, 'business.academy_enrollment_terminal');
        }

        $data = $request->validated();
        if ($data['status'] === 'completed' && empty($data['completed_on'])) {
            $data['completed_on'] = now()->toDateString();
        }

        $academyEnrollment->update($data);
        $academyEnrollment->load(self::RELATIONS);

        return response()->json([
            'success' => true,
            'data' => (new AcademyEnrollmentResource($academyEnrollment))->resolve($request),
        ]);
    }
}
