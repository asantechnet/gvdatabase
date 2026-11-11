<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAcademyAttendanceRequest;
use App\Http\Resources\AcademyAttendanceResource;
use App\Models\AcademyAttendance;
use App\Models\AcademyClass;
use App\Models\AcademyEnrollment;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcademyAttendanceController extends Controller
{
    public function month(Request $request): JsonResponse
    {
        $data = $request->validate([
            'class_id' => ['required', 'integer', 'exists:academy_classes,id'],
            'month' => ['required', 'date_format:Y-m'],
        ]);
        $class = AcademyClass::with(['program', 'teachers'])
            ->withCount(['enrollments as seats_taken' => fn ($query) => $query->where('status', 'active')])
            ->findOrFail($data['class_id']);
        $start = CarbonImmutable::createFromFormat('Y-m-d', $data['month'].'-01')->startOfMonth();
        $end = $start->endOfMonth();
        $enrollments = AcademyEnrollment::with(['student', 'line.currency'])
            ->where('class_id', $class->id)
            ->whereIn('status', ['active', 'completed'])
            ->whereDate('started_on', '<=', $end->toDateString())
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $start->toDateString()))
            ->orderBy('started_on')->orderBy('id')->get();
        $attendance = AcademyAttendance::query()
            ->where('class_id', $class->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (AcademyAttendance $row) => $row->academy_enrollment_id.'|'.$row->date->toDateString());

        return response()->json(['success' => true, 'data' => new AcademyAttendanceResource([
            'class' => $class,
            'month' => $start->format('Y-m'),
            'dates' => collect(range(1, $start->daysInMonth))->map(fn (int $day) => $start->setDay($day)->toDateString())->all(),
            'rows' => $enrollments->map(function (AcademyEnrollment $enrollment) use ($attendance): array {
                $cells = [];
                foreach ($attendance as $key => $row) {
                    if (str_starts_with($key, $enrollment->id.'|')) {
                        $cells[$row->date->toDateString()] = [
                            'id' => $row->id,
                            'status' => $row->status,
                            'check_in_time' => $row->check_in_time,
                            'check_out_time' => $row->check_out_time,
                        ];
                    }
                }

                return [
                    'enrollment_id' => $enrollment->id,
                    'student_number' => $enrollment->student?->student_number,
                    'name' => $enrollment->student?->name ?? $enrollment->applicant?->name,
                    'contact' => $enrollment->student?->contact ?? $enrollment->applicant?->contact,
                    'started_on' => $enrollment->started_on?->toDateString(),
                    'ends_on' => $enrollment->ends_on?->toDateString(),
                    'status' => $enrollment->status,
                    'balance' => $enrollment->line?->remainingTotal() ?? 0,
                    'currency' => $enrollment->line?->currency?->only(['code', 'symbol', 'decimal_places']),
                    'attendance' => $cells,
                ];
            })->values()->all(),
        ])]);
    }

    public function save(StoreAcademyAttendanceRequest $request): JsonResponse
    {
        $rows = $request->validated()['rows'];
        DB::transaction(function () use ($rows, $request): void {
            foreach ($rows as $row) {
                $enrollment = AcademyEnrollment::query()->findOrFail($row['enrollment_id']);
                AcademyAttendance::updateOrCreate(
                    ['academy_enrollment_id' => $enrollment->id, 'date' => $row['date']],
                    [
                        'class_id' => $enrollment->class_id,
                        'status' => $row['status'],
                        'check_in_time' => $row['status'] === 'present' ? ($row['check_in_time'] ?? $enrollment->academyClass?->starts_at) : null,
                        'check_out_time' => $row['status'] === 'present' ? ($row['check_out_time'] ?? $enrollment->academyClass?->ends_at) : null,
                        'recorded_by' => $request->user()->id,
                    ],
                );
            }
        });

        return response()->json(['success' => true, 'data' => ['saved' => count($rows)]]);
    }

    public function destroy(AcademyAttendance $academyAttendance): JsonResponse
    {
        $academyAttendance->delete();

        return response()->json(['success' => true, 'data' => null]);
    }

}
