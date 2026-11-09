<?php

namespace App\Services;

use App\Models\AcademyCertificate;
use App\Models\AcademyCertificateTemplate;
use App\Models\AcademyEnrollment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcademyCertificateService
{
    public function issue(AcademyEnrollment $enrollment, ?int $templateId, User $actor): AcademyCertificate
    {
        $enrollment->loadMissing(['student.applicant', 'applicant', 'academyClass.program', 'academyClass.teachers', 'contract', 'line']);
        if ($enrollment->status !== 'completed') {
            throw ValidationException::withMessages(['academy_enrollment_id' => ['business.academy_certificate_not_eligible']]);
        }
        $student = $enrollment->student;
        if (! $student) {
            throw ValidationException::withMessages(['academy_enrollment_id' => ['business.academy_certificate_not_eligible']]);
        }
        // A direct Academy student is enrolled without an applicant or a
        // contract. The durable student record is the certificate identity, so
        // the applicant link is optional and copied only when it exists.
        $applicant = $enrollment->applicant ?? $student->applicant;
        if (AcademyCertificate::withTrashed()->where('academy_enrollment_id', $enrollment->id)->exists()) {
            throw ValidationException::withMessages(['academy_enrollment_id' => ['business.academy_certificate_duplicate']]);
        }

        $template = $this->template($templateId);

        return DB::transaction(function () use ($enrollment, $template, $actor, $applicant, $student): AcademyCertificate {
            $setting = Setting::query()->latest('id')->first();
            $signatoryName = $setting?->original_company_name ?: $setting?->short_company_name ?: $setting?->website_title;
            $signatoryTitle = $template->signatory_title;
            $certificate = AcademyCertificate::create([
                'academy_enrollment_id' => $enrollment->id,
                'applicant_id' => $applicant?->id,
                'class_id' => $enrollment->class_id,
                'contract_id' => $enrollment->contract_id,
                'applicant_contract_id' => $enrollment->applicant_contract_id,
                'academy_certificate_template_id' => $template->id,
                'issued_at' => now(),
                'issued_by' => $actor->id,
                'snapshot' => [],
                'status' => 'issued',
            ]);
            $certificate->refresh();

            $values = [
                'student_name' => $student->name,
                'father_name' => $student->father_name,
                'student_id' => $student->student_number,
                'program' => $enrollment->academyClass?->program?->name,
                'class' => $enrollment->academyClass?->name,
                'teachers' => $enrollment->academyClass?->teachers->pluck('name')->join(', ') ?: null,
                'start_date' => $enrollment->started_on?->format('Y-m-d'),
                'end_date' => $enrollment->ends_on?->format('Y-m-d'),
                'completion_date' => $enrollment->completed_on?->format('Y-m-d'),
                'certificate_number' => $certificate->certificate_number,
                'issue_date' => $certificate->issued_at?->format('Y-m-d'),
            ];
            // Printed prose must never collapse to an empty gap when an optional
            // academy field is missing; the snapshot is immutable, so make the
            // fallback explicit at issue time.
            $bodyValues = array_map(
                fn (mixed $value) => is_string($value) && trim($value) === '' ? '—' : ($value ?? '—'),
                $values,
            );
            $certificate->update(['snapshot' => [
                'award' => [
                    'student_name' => $values['student_name'],
                    'father_name' => $values['father_name'],
                    'student_id' => $values['student_id'],
                    'program' => $values['program'],
                    'class' => $values['class'],
                    'teachers' => $enrollment->academyClass?->teachers->pluck('name')->values()->all() ?? [],
                    'start_date' => $values['start_date'],
                    'end_date' => $values['end_date'],
                    'completion_date' => $values['completion_date'],
                ],
                'certificate_number' => $certificate->certificate_number,
                'issue_date' => $values['issue_date'],
                'signatory' => ['name' => $signatoryName, 'title' => $signatoryTitle],
                'template' => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'title' => $template->title,
                    'body' => $template->body,
                    'signatory_title' => $template->signatory_title,
                    'version' => $template->updated_at?->toIso8601String() ?? 'v1',
                ],
                'rendered_body' => $template->renderBody($bodyValues),
            ]]);

            return $certificate->fresh();
        });
    }

    /** @param list<int> $enrollmentIds */
    public function bulkIssue(int $classId, array $enrollmentIds, ?int $templateId, User $actor): array
    {
        $created = 0;
        $skipped = 0;
        $certificates = [];
        foreach (array_values(array_unique($enrollmentIds)) as $id) {
            $enrollment = AcademyEnrollment::query()->findOrFail($id);
            if ((int) $enrollment->class_id !== $classId) {
                throw ValidationException::withMessages(['enrollment_ids' => ['business.academy_enrollment_invalid']]);
            }
            if (AcademyCertificate::withTrashed()->where('academy_enrollment_id', $id)->exists()) {
                $skipped++;
                continue;
            }
            $certificates[] = $this->issue($enrollment, $templateId, $actor);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped, 'certificates' => $certificates];
    }

    public function revoke(AcademyCertificate $certificate, string $reason, User $actor): AcademyCertificate
    {
        if ($certificate->status !== 'issued') {
            throw ValidationException::withMessages(['reason' => ['business.academy_certificate_revoked']]);
        }
        $certificate->update([
            'status' => 'revoked',
            'revoked_at' => now(),
            'revoked_by' => $actor->id,
            'revocation_reason' => $reason,
        ]);

        return $certificate->fresh();
    }

    private function template(?int $templateId): AcademyCertificateTemplate
    {
        $template = $templateId
            ? AcademyCertificateTemplate::query()->whereKey($templateId)->where('is_active', true)->first()
            : AcademyCertificateTemplate::query()->where('is_active', true)->orderBy('id')->first();

        if (! $template) {
            throw ValidationException::withMessages(['template_id' => ['business.academy_certificate_template_missing']]);
        }

        return $template;
    }
}
