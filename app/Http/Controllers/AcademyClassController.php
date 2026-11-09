<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAcademyClassRequest;
use App\Http\Requests\UpdateAcademyClassRequest;
use App\Http\Resources\AcademyClassResource;
use App\Http\Resources\AcademyEnrollmentResource;
use App\Models\AcademyClass;
use App\Services\CommissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcademyClassController extends Controller
{
    private const RELATIONS = ['program', 'teachers'];

    public function index(Request $request): JsonResponse
    {
        $query = AcademyClass::with(self::RELATIONS)
            ->withCount(['enrollments as seats_taken' => fn ($query) => $query->where('status', 'active')]);

        // A teacher sees only the classes they are assigned to. Staff with the
        // Academy contracts surface retain the full class list.
        if ($request->user()?->hasPermission('view_academy_attendance')
            && ! $request->user()?->hasPermission('view_academy_contracts')) {
            $employeeId = $request->user()?->employee?->id;
            $query->whereHas('teachers', fn ($teacher) => $teacher->where('employees.id', $employeeId));
        }

        $query->when($request->filled('search'), function ($query) use ($request): void {
            $search = $request->string('search')->toString();
            $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%"));
        })->when($request->filled('program_id'), fn ($query) => $query->where('program_id', $request->integer('program_id')))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('sort_order')->orderBy('name');

        return response()->json([
            'success' => true,
            'data' => AcademyClassResource::collection($query->get())->resolve($request),
        ]);
    }

    public function show(Request $request, AcademyClass $academyClass): JsonResponse
    {
        $academyClass->load(self::RELATIONS)
            ->loadCount(['enrollments as seats_taken' => fn ($query) => $query->where('status', 'active')]);

        $roster = $academyClass->enrollments()
            ->with(['student', 'applicant', 'contract', 'line.currency', 'academyClass.program', 'academyClass.teachers'])
            ->orderBy('started_on')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                ...(new AcademyClassResource($academyClass))->resolve($request),
                'roster' => AcademyEnrollmentResource::collection($roster)->resolve($request),
            ],
        ]);
    }

    public function store(StoreAcademyClassRequest $request): JsonResponse
    {
        $class = DB::transaction(function () use ($request): AcademyClass {
            $data = $request->validated();
            $teachers = $data['teacher_ids'] ?? [];
            unset($data['teacher_ids']);

            $class = AcademyClass::create($data);
            $class->teachers()->sync($teachers);

            return $class;
        });

        return response()->json([
            'success' => true,
            'data' => (new AcademyClassResource($class->load(self::RELATIONS)->loadCount(['enrollments as seats_taken' => fn ($query) => $query->where('status', 'active')])))->resolve($request),
        ], 201);
    }

    public function update(UpdateAcademyClassRequest $request, AcademyClass $academyClass): JsonResponse
    {
        DB::transaction(function () use ($request, $academyClass): void {
            $data = $request->validated();
            $teachers = array_key_exists('teacher_ids', $data) ? $data['teacher_ids'] : null;
            unset($data['teacher_ids']);

            $academyClass->update($data);
            if ($teachers !== null) {
                $academyClass->teachers()->sync($teachers);
            }
        });

        return response()->json([
            'success' => true,
            'data' => (new AcademyClassResource($academyClass->fresh()->load(self::RELATIONS)->loadCount(['enrollments as seats_taken' => fn ($query) => $query->where('status', 'active')])))->resolve($request),
        ]);
    }

    /**
     * Mark a class completed and accrue its teachers' commission.
     *
     * Teachers may complete only a class assigned to them; Academy staff may
     * complete any class. The operation is idempotent.
     */
    public function complete(Request $request, AcademyClass $academyClass, CommissionService $commissions): JsonResponse
    {
        $user = $request->user();
        $employeeId = $user?->employee?->id;
        if (! $user?->hasPermission('manage_academy_classes')
            && ($employeeId === null || ! $academyClass->teachers()->whereKey($employeeId)->exists())) {
            abort(403, 'auth.forbidden');
        }

        DB::transaction(function () use ($academyClass, $commissions): void {
            $class = AcademyClass::query()->lockForUpdate()->findOrFail($academyClass->id);
            if ($class->completed_at === null) {
                $class->update(['completed_at' => now()]);
            }
            $commissions->syncForAcademyClass($class->fresh('teachers.serviceCommissions'));
        });

        return response()->json([
            'success' => true,
            'data' => (new AcademyClassResource($academyClass->fresh()->load(self::RELATIONS)))->resolve($request),
        ]);
    }

    public function destroy(AcademyClass $academyClass): JsonResponse
    {
        if ($academyClass->enrollments()->exists()) {
            abort(422, 'business.academy_class_in_use');
        }

        $academyClass->delete();

        return response()->json(['success' => true]);
    }
}
