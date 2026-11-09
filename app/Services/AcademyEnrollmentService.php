<?php

namespace App\Services;

use App\Models\AcademyClass;
use App\Models\AcademyEnrollment;
use App\Models\Applicant;
use App\Models\ApplicantContract;
use App\Models\Contract;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The single writer for Academy terms and enrollments.
 *
 * Start is the contract's sale date and end is the class end. No
 * client-supplied date is accepted.
 */
class AcademyEnrollmentService
{
    /** @return array{started_on: string, ends_on: string} */
    public function termFor(AcademyClass $class, CarbonImmutable $start): array
    {
        $end = $class->ends_on?->toDateString();

        if ($end === null || $end < $start->toDateString()) {
            abort(422, 'business.academy_term_undefined');
        }

        if ($class->starts_on !== null && $end < $class->starts_on->toDateString()) {
            abort(422, 'business.academy_term_undefined');
        }

        return ['started_on' => $start->toDateString(), 'ends_on' => $end];
    }

    public function createForLine(Contract $contract, ApplicantContract $line, Applicant $applicant, array $data): AcademyEnrollment
    {
        return DB::transaction(function () use ($contract, $line, $applicant, $data): AcademyEnrollment {
            $class = AcademyClass::query()->lockForUpdate()->findOrFail($data['class_id']);
            $start = CarbonImmutable::parse($contract->start_date->toDateString());
            $term = $this->termFor($class, $start);
            $this->assertSeatAvailable($class);

            $student = $this->studentFor($applicant, $data['student_id'] ?? null);
            $this->assertStudentNotEnrolled($student, $class);
            $this->syncStudentNumberOnDetail($applicant, $student);
            $enrollment = AcademyEnrollment::query()->create([
                'student_id' => $student->id,
                'applicant_id' => $applicant->id,
                'contract_id' => $contract->id,
                'applicant_contract_id' => $line->id,
                'class_id' => $class->id,
                'program_id' => $data['program_id'] ?? $class->program_id,
                'started_on' => $term['started_on'],
                'ends_on' => $term['ends_on'],
                'status' => 'active',
            ]);

            $contract->update(['end_date' => $term['ends_on']]);

            return $enrollment;
        });
    }

    public function updateForLine(Contract $contract, ApplicantContract $line, Applicant $applicant, array $data): AcademyEnrollment
    {
        return DB::transaction(function () use ($contract, $line, $applicant, $data): AcademyEnrollment {
            $enrollment = AcademyEnrollment::query()->where('applicant_contract_id', $line->id)->lockForUpdate()->first();
            if (! $enrollment) {
                return $this->createForLine($contract, $line, $applicant, $data);
            }

            $class = AcademyClass::query()->lockForUpdate()->findOrFail($data['class_id']);
            $start = CarbonImmutable::parse($contract->start_date->toDateString());
            $term = $this->termFor($class, $start);
            $this->assertSeatAvailable($class, $enrollment->id);

            $student = $this->studentFor($applicant, $data['student_id'] ?? null);
            $this->assertStudentNotEnrolled($student, $class, $enrollment->id);
            $this->syncStudentNumberOnDetail($applicant, $student);
            $enrollment->update([
                'student_id' => $student->id,
                'applicant_id' => $applicant->id,
                'contract_id' => $contract->id,
                'class_id' => $class->id,
                'program_id' => $data['program_id'] ?? $class->program_id,
                'started_on' => $term['started_on'],
                'ends_on' => $term['ends_on'],
            ]);

            $contract->update(['end_date' => $term['ends_on']]);

            return $enrollment->refresh();
        });
    }

    /** @return array<string, mixed> */
    public function snapshot(AcademyEnrollment $enrollment): array
    {
        $enrollment->loadMissing(['student', 'academyClass.program', 'academyClass.teachers']);

        return [
            'enrollment_id' => $enrollment->id,
            'student_record_id' => $enrollment->student_id,
            'student_id' => $enrollment->student?->student_number,
            'class_id' => $enrollment->class_id,
            'class_name' => $enrollment->academyClass?->name,
            'program' => $enrollment->academyClass?->program?->name,
            'teachers' => $enrollment->academyClass?->teachers?->pluck('name')->values()->all() ?? [],
            'started_on' => $enrollment->started_on?->toDateString(),
            'ends_on' => $enrollment->ends_on?->toDateString(),
        ];
    }

    /**
     * One student cannot hold a second live or completed enrollment in the
     * same class. Withdrawn and cancelled attempts are not a completed class
     * and may be retried; soft-deleted rows do not block a new enrollment.
     */
    private function assertStudentNotEnrolled(Student $student, AcademyClass $class, ?int $excluding = null): void
    {
        $duplicate = AcademyEnrollment::query()
            ->where('student_id', $student->id)
            ->where('class_id', $class->id)
            ->whereIn('status', ['active', 'completed'])
            ->when($excluding, fn ($query) => $query->whereKeyNot($excluding))
            ->lockForUpdate()
            ->first();

        abort_if($duplicate !== null, 422, 'business.academy_enrollment_duplicate');
    }

    private function assertSeatAvailable(AcademyClass $class, ?int $excluding = null): void
    {
        if ($class->capacity === null) {
            return;
        }

        $active = AcademyEnrollment::query()
            ->where('class_id', $class->id)
            ->where('status', 'active')
            ->when($excluding, fn ($query) => $query->whereKeyNot($excluding))
            ->count();

        if ($active >= $class->capacity) {
            abort(422, 'business.academy_class_full');
        }
    }

    /**
     * Reuse the applicant's durable student row or create it from the contract
     * identity. Older clients may still supply a number; otherwise the student
     * gets a generated number that slips and certificates can print without
     * asking the operator for it.
     */
    private function studentFor(Applicant $applicant, ?string $studentNumber): Student
    {
        $identity = [
            'name' => $applicant->name,
            'father_name' => $applicant->father_name,
            'father_phone' => $applicant->father_phone,
            'contact' => $applicant->contact,
            'email' => $applicant->email,
            'address' => $applicant->address,
        ];
        $supplied = trim((string) $studentNumber);
        $student = Student::query()->where('applicant_id', $applicant->id)->first();

        if ($student) {
            $student->fill($identity);
            if ($student->student_number === null) {
                $student->student_number = $supplied !== '' ? $supplied : null;
            }
            $student->save();

            if ($student->student_number === null) {
                $student->update(['student_number' => $this->generatedStudentNumber($student)]);
            }

            return $student->refresh();
        }

        $student = Student::query()->create([
            ...$identity,
            'student_number' => $supplied !== '' ? $supplied : null,
            'applicant_id' => $applicant->id,
        ]);

        if ($student->student_number === null) {
            $student->update(['student_number' => $this->generatedStudentNumber($student)]);
        }

        return $student->refresh();
    }

    /** The student row id is unique, so it is the safe generated-number base. */
    private function generatedStudentNumber(Student $student): string
    {
        $base = 'STU-'.str_pad((string) $student->id, 6, '0', STR_PAD_LEFT);
        $number = $base;
        $suffix = 1;

        while (Student::withTrashed()->where('student_number', $number)->whereKeyNot($student->id)->exists()) {
            $number = $base.'-'.(++$suffix);
        }

        return $number;
    }

    /** Keep the legacy academy detail's student number aligned with the student row. */
    private function syncStudentNumberOnDetail(Applicant $applicant, Student $student): void
    {
        if ($student->student_number === null) {
            return;
        }

        $applicant->academyDetail()->updateOrCreate([], ['student_id' => $student->student_number]);
    }
}
