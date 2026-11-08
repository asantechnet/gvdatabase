<?php

namespace App\Services;

use App\Models\AcademyClass;
use App\Models\AcademyEnrollment;
use App\Models\Applicant;
use App\Models\ApplicantContract;
use App\Models\Contract;
use App\Models\Duration;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The single writer for Academy terms and enrollments.
 *
 * Start is the contract's sale date. End is the earlier of the class end and
 * the sold duration's month count, so an enrollment never outlives either
 * promise. No client-supplied date is accepted.
 */
class AcademyEnrollmentService
{
    /** @return array{started_on: string, ends_on: string} */
    public function termFor(AcademyClass $class, ?Duration $duration, CarbonImmutable $start): array
    {
        $classEnd = $class->ends_on?->toDateString();

        if ($classEnd !== null && $classEnd < $start->toDateString()) {
            abort(422, 'business.academy_class_ended');
        }

        $durationEnd = $duration?->months
            ? $start->addMonthsNoOverflow((int) $duration->months)->toDateString()
            : null;

        $end = collect([$classEnd, $durationEnd])->filter()->sort()->first();

        if ($end === null) {
            abort(422, 'business.academy_term_undefined');
        }

        if ($end < $start->toDateString()) {
            abort(422, 'business.academy_term_undefined');
        }

        return ['started_on' => $start->toDateString(), 'ends_on' => $end];
    }

    public function createForLine(Contract $contract, ApplicantContract $line, Applicant $applicant, array $data): AcademyEnrollment
    {
        return DB::transaction(function () use ($contract, $line, $applicant, $data): AcademyEnrollment {
            $class = AcademyClass::query()->lockForUpdate()->findOrFail($data['class_id']);
            $duration = ! empty($data['duration_id']) ? Duration::query()->find($data['duration_id']) : $class->duration;
            $start = CarbonImmutable::parse($contract->start_date->toDateString());
            $term = $this->termFor($class, $duration, $start);
            $this->assertSeatAvailable($class);

            $student = $this->studentFor($applicant, (string) $data['student_id']);
            $enrollment = AcademyEnrollment::query()->create([
                'student_id' => $student->id,
                'applicant_id' => $applicant->id,
                'contract_id' => $contract->id,
                'applicant_contract_id' => $line->id,
                'class_id' => $class->id,
                'program_id' => $data['program_id'] ?? $class->program_id,
                'time_id' => $data['time_id'] ?? $class->time_id,
                'duration_id' => $duration?->id ?? $class->duration_id,
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
            $duration = ! empty($data['duration_id']) ? Duration::query()->find($data['duration_id']) : $class->duration;
            $start = CarbonImmutable::parse($contract->start_date->toDateString());
            $term = $this->termFor($class, $duration, $start);
            $this->assertSeatAvailable($class, $enrollment->id);

            $student = $this->studentFor($applicant, (string) $data['student_id']);
            $enrollment->update([
                'student_id' => $student->id,
                'applicant_id' => $applicant->id,
                'contract_id' => $contract->id,
                'class_id' => $class->id,
                'program_id' => $data['program_id'] ?? $class->program_id,
                'time_id' => $data['time_id'] ?? $class->time_id,
                'duration_id' => $duration?->id ?? $class->duration_id,
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
        $enrollment->loadMissing(['student', 'academyClass.program', 'academyClass.time', 'academyClass.teachers', 'duration']);

        return [
            'enrollment_id' => $enrollment->id,
            'student_record_id' => $enrollment->student_id,
            'student_id' => $enrollment->student?->student_number,
            'class_id' => $enrollment->class_id,
            'class_name' => $enrollment->academyClass?->name,
            'program' => $enrollment->academyClass?->program?->name,
            'time' => $enrollment->academyClass?->time?->name,
            'duration' => $enrollment->duration?->name,
            'teachers' => $enrollment->academyClass?->teachers?->pluck('name')->values()->all() ?? [],
            'started_on' => $enrollment->started_on?->toDateString(),
            'ends_on' => $enrollment->ends_on?->toDateString(),
        ];
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

    private function studentFor(Applicant $applicant, string $studentNumber): Student
    {
        $student = Student::query()->where('applicant_id', $applicant->id)->first();

        if ($student) {
            if ($student->student_number === null && $studentNumber !== '') {
                $student->update(['student_number' => $studentNumber]);
            }

            return $student;
        }

        return Student::query()->create([
            'student_number' => $studentNumber !== '' ? $studentNumber : null,
            'applicant_id' => $applicant->id,
            'name' => $applicant->name,
            'father_name' => $applicant->father_name,
            'father_phone' => $applicant->father_phone,
            'contact' => $applicant->contact,
            'email' => $applicant->email,
            'address' => $applicant->address,
        ]);
    }
}
