<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAcademyStudentRequest;
use App\Http\Resources\AcademyEnrollmentResource;
use App\Models\AcademyClass;
use App\Models\AcademyEnrollment;
use App\Models\Income;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * A direct Academy student. Enrollment is the record and the slip is its
 * document; no contract is created for this path.
 */
class AcademyStudentController extends Controller
{
    public function store(StoreAcademyStudentRequest $request): JsonResponse
    {
        $enrollment = DB::transaction(function () use ($request): AcademyEnrollment {
            $data = $request->validated();
            $class = AcademyClass::query()->lockForUpdate()->findOrFail($data['class_id']);
            if ($class->capacity !== null) {
                $active = AcademyEnrollment::query()
                    ->where('class_id', $class->id)
                    ->where('status', 'active')
                    ->count();
                abort_if($active >= $class->capacity, 422, 'business.academy_class_full');
            }

            $startedOn = $data['started_on'] ?? now()->toDateString();
            // The class owns the end date. Enrollment stores the derived value
            // as an immutable historical snapshot for attendance, certificates
            // and slips, never as a direct-student form field.
            $endsOn = $class->ends_on?->toDateString();
            if ($endsOn !== null && $endsOn < $startedOn) {
                abort(422, 'business.academy_term_undefined');
            }

            $student = Student::query()->create([
                'name' => $data['name'],
                'father_name' => $data['father_name'] ?? null,
                'father_phone' => $data['father_phone'] ?? null,
                'contact' => $data['contact'] ?? null,
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'notes' => $data['notes'] ?? null,
                'is_active' => true,
            ]);
            $student->update(['student_number' => $this->generatedStudentNumber($student)]);

            return AcademyEnrollment::query()->create([
                'student_id' => $student->id,
                'class_id' => $class->id,
                'program_id' => $class->program_id,
                'started_on' => $startedOn,
                'ends_on' => $endsOn,
                'status' => 'active',
                'notes' => $data['notes'] ?? null,
            ]);
        });

        $enrollment->load([
            'student', 'academyClass.program', 'academyClass.teachers',
            'academyClass' => fn ($class) => $class->withCount(['enrollments as seats_taken' => fn ($query) => $query->where('status', 'active')]),
        ]);

        return response()->json([
            'success' => true,
            'data' => (new AcademyEnrollmentResource($enrollment))->resolve($request),
        ], 201);
    }

    /**
     * Payments that belong to this student: direct student receipts plus the
     * Academy lines of a linked applicant. Other services on the same applicant
     * stay out of the student ledger.
     */
    public function finance(Student $student): JsonResponse
    {
        $student->loadMissing('applicant');

        $incomes = Income::query()
            ->with(['incomeCurrency', 'applicantContract'])
            ->where(function ($query) use ($student): void {
                $query->where('student_id', $student->id);

                if ($student->applicant_id) {
                    $query->orWhere(function ($query) use ($student): void {
                        $query->where('applicant_id', $student->applicant_id)
                            ->whereHas('applicantContract', fn ($line) => $line->where('service', 'academy'));
                    });
                }
            })
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        $totals = $incomes->where('status', Income::APPROVED)
            ->groupBy(fn (Income $income) => $income->currency_id)
            ->map(function ($rows): array {
                $currency = $rows->first()->incomeCurrency;

                return [
                    'currency' => $currency ? [
                        'id' => $currency->id,
                        'code' => $currency->code,
                        'symbol' => $currency->symbol,
                        'decimal_places' => (int) $currency->decimal_places,
                    ] : null,
                    'amount' => round($rows->sum(fn (Income $income) => (float) $income->amount * (int) $income->quantity), 6),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'student' => [
                    'id' => $student->id,
                    'student_number' => $student->student_number,
                    'name' => $student->name,
                    'father_name' => $student->father_name,
                    'father_phone' => $student->father_phone,
                    'contact' => $student->contact,
                    'email' => $student->email,
                    'address' => $student->address,
                    'applicant_id' => $student->applicant_id,
                ],
                'totals' => $totals,
                'incomes' => $incomes->map(fn (Income $income) => [
                    'id' => $income->id,
                    'receipt_number' => $income->receipt_number,
                    'type' => $income->type,
                    'description' => $income->description,
                    'amount' => (float) $income->amount,
                    'quantity' => (int) $income->quantity,
                    'line_total' => round((float) $income->amount * (int) $income->quantity, 6),
                    'date' => $income->date?->toDateString(),
                    'status' => $income->status,
                    'applicant_contract_id' => $income->applicant_contract_id,
                    'currency' => $income->incomeCurrency ? [
                        'id' => $income->incomeCurrency->id,
                        'code' => $income->incomeCurrency->code,
                        'symbol' => $income->incomeCurrency->symbol,
                        'decimal_places' => (int) $income->incomeCurrency->decimal_places,
                    ] : null,
                ])->values(),
            ],
        ]);
    }

    private function generatedStudentNumber(Student $student): string
    {
        $year = ($student->created_at ?? now())->format('y');
        $base = sprintf('STU-%s-%03d', $year, $student->id % 1000);
        $number = $base;
        $suffix = 1;

        while (Student::withTrashed()->where('student_number', $number)->whereKeyNot($student->id)->exists()) {
            $number = $base.'-'.(++$suffix);
        }

        return $number;
    }
}
