<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Copy legacy academy details into durable students and enrollments.
 *
 * The backfill is intentionally conservative: a line is linked only when the
 * applicant has exactly one live academy contract line. Ambiguous details keep
 * their enrollment but leave the contract/line columns null. Re-running the
 * migration never creates a second enrollment or overwrites a known month.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->backfillDurationMonths();
        $this->backfillStudentsAndEnrollments();
    }

    public function down(): void
    {
        // The schema migration owns the tables. Keeping the backfilled rows
        // here makes a partial rollback non-destructive and reviewable.
    }

    private function backfillDurationMonths(): void
    {
        $unknown = [];

        DB::table('durations')->orderBy('id')->chunkById(200, function ($durations) use (&$unknown): void {
            foreach ($durations as $duration) {
                if ($duration->months !== null) {
                    continue;
                }

                $name = trim((string) $duration->name);
                if (preg_match('/^(\d{1,2})\s*months?$/i', $name, $matches)) {
                    $months = (int) $matches[1];
                    if ($months >= 1 && $months <= 60) {
                        DB::table('durations')->where('id', $duration->id)->update(['months' => $months]);
                        continue;
                    }
                }

                $unknown[] = $name;
            }
        });

        if ($unknown !== []) {
            Log::info('Academy duration-month backfill left unknown names unchanged.', [
                'durations' => array_values(array_unique($unknown)),
            ]);
        }
    }

    private function backfillStudentsAndEnrollments(): void
    {
        $unresolved = [];

        DB::table('academy_applicant_details')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById(200, function ($details) use (&$unresolved): void {
                foreach ($details as $detail) {
                    $applicant = DB::table('applicants')->where('id', $detail->applicant_id)->first();
                    if (! $applicant) {
                        $unresolved[] = ['applicant_id' => $detail->applicant_id, 'reason' => 'missing applicant'];
                        continue;
                    }

                    $studentId = $this->studentFor($applicant, $detail->student_id ?? null);

                    if ($detail->class_id === null) {
                        continue;
                    }

                    $line = $this->singleAcademyLine((int) $detail->applicant_id);
                    $contract = $line
                        ? DB::table('contracts')->where('id', $line->contract_id)->first()
                        : null;

                    $startedOn = $contract?->start_date
                        ?: DB::table('academy_classes')->where('id', $detail->class_id)->value('starts_on');
                    $class = DB::table('academy_classes')->where('id', $detail->class_id)->first();
                    $status = $contract && $contract->status === 'completed' ? 'completed' : 'active';

                    $lookup = $line
                        ? ['applicant_contract_id' => $line->id]
                        : [
                            'student_id' => $studentId,
                            'class_id' => $detail->class_id,
                            'applicant_id' => $detail->applicant_id,
                        ];

                    $existing = DB::table('academy_enrollments')->where($lookup)->first();
                    if ($existing) {
                        continue;
                    }

                    DB::table('academy_enrollments')->insert([
                        'student_id' => $studentId,
                        'applicant_id' => $detail->applicant_id,
                        'contract_id' => $contract?->id,
                        'applicant_contract_id' => $line?->id,
                        'class_id' => $detail->class_id,
                        'program_id' => $detail->program_id,
                        'time_id' => $detail->time_id,
                        'duration_id' => $detail->duration_id,
                        'started_on' => $startedOn,
                        'ends_on' => $class?->ends_on,
                        'status' => $status,
                        'completed_on' => $status === 'completed' ? ($contract?->updated_at ?? now()) : null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    if (! $line) {
                        $unresolved[] = [
                            'applicant_id' => $detail->applicant_id,
                            'reason' => 'ambiguous or missing academy contract line',
                        ];
                    }
                }
            });

        if ($unresolved !== []) {
            Log::warning('Academy enrollment backfill left unresolved links.', ['rows' => $unresolved]);
        }
    }

    private function studentFor(object $applicant, ?string $studentNumber): int
    {
        $student = DB::table('students')->where('applicant_id', $applicant->id)->whereNull('deleted_at')->first();

        $attributes = [
            'applicant_id' => $applicant->id,
            'name' => $applicant->name,
            'father_name' => $applicant->father_name,
            'father_phone' => $applicant->father_phone,
            'contact' => $applicant->contact,
            'email' => $applicant->email,
            'address' => $applicant->address,
            'updated_at' => now(),
        ];

        if ($student) {
            if ($student->student_number === null && $studentNumber !== null) {
                $attributes['student_number'] = $studentNumber;
            }
            DB::table('students')->where('id', $student->id)->update($attributes);

            return (int) $student->id;
        }

        return (int) DB::table('students')->insertGetId([
            ...$attributes,
            'student_number' => $studentNumber,
            'is_active' => true,
            'created_at' => now(),
        ]);
    }

    private function singleAcademyLine(int $applicantId): ?object
    {
        $lines = DB::table('applicant_contract')
            ->join('contracts', 'contracts.id', '=', 'applicant_contract.contract_id')
            ->where('applicant_contract.applicant_id', $applicantId)
            ->where('applicant_contract.service', 'academy')
            ->whereNull('applicant_contract.deleted_at')
            ->whereNull('contracts.deleted_at')
            ->where('contracts.is_cancelled', false)
            ->get(['applicant_contract.id', 'applicant_contract.contract_id']);

        return $lines->count() === 1 ? $lines->first() : null;
    }
};
