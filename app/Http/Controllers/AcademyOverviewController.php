<?php

namespace App\Http\Controllers;

use App\Http\Resources\AcademyClassResource;
use App\Models\AcademyClass;
use App\Models\AcademyEnrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcademyOverviewController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $active = AcademyEnrollment::query()->where('status', 'active');
        $classes = AcademyClass::query()->with(['program', 'teachers'])
            ->withCount(['enrollments as seats_taken' => fn ($query) => $query->where('status', 'active')])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $activeWithLines = (clone $active)->with('line')->get();
        $outstanding = $activeWithLines->sum(fn (AcademyEnrollment $enrollment) => $enrollment->line?->remainingTotal() ?? 0);

        $today = now()->isoWeekday();
        $todayClasses = $classes
            ->filter(fn (AcademyClass $class) => in_array($today, $class->weekdays ?? [], true))
            ->sortBy('starts_at')
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'active_students' => (clone $active)->distinct()->count('student_id'),
                'active_enrollments' => (clone $active)->count(),
                'classes' => $classes->count(),
                'seats_filled' => (clone $active)->count(),
                'capacity' => $classes->whereNotNull('capacity')->sum('capacity'),
                'ending_soon' => (clone $active)->whereNotNull('ends_on')
                    ->whereBetween('ends_on', [now()->toDateString(), now()->addDays(30)->toDateString()])
                    ->count(),
                'outstanding' => round((float) $outstanding, 2),
                'completed_this_month' => AcademyEnrollment::query()
                    ->where('status', 'completed')
                    ->whereBetween('completed_on', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                    ->count(),
                'today_classes' => AcademyClassResource::collection($todayClasses)->resolve($request),
            ],
        ]);
    }
}
