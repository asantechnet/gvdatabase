<?php

namespace App\Services;

use App\Models\Applicant;
use App\Support\BuiltInService;

/**
 * Writes the per-service half of an applicant.
 *
 * The applicant row holds shared identity (name, guardian, contact, address,
 * portal user, employee). Each built-in service asks for a different
 * field set that lives in its own 1:1 detail table, and the multi-valued fields
 * (favourite subjects, skills, teachers) live in pivots. One writer serves both
 * the create and the update path so they cannot drift.
 *
 * Money is deliberately not here: the applicant's price, currency, rate,
 * discount and payment state belong to the `applicant_contract` line.
 */
class ApplicantServiceDetails
{
    /** Payload keys each service persists into its detail row. */
    private const ATTRIBUTES = [
        BuiltInService::SCHOLARSHIP => [
            'passport_id', 'grade_score', 'country_id', 'contract_type_id',
            'education_level_id', 'semester_id', 'university_id', 'university_fee', 'scholarship_id',
        ],
        BuiltInService::PRIVATE => ['passport_id', 'grade_score', 'country_id', 'contract_type_id', 'education_level_id', 'semester_id', 'university_id', 'university_fee'],
        BuiltInService::TEST_OFFER => ['passport_id', 'test_id'],
        BuiltInService::ACADEMY => ['student_id', 'program_id', 'class_id'],
    ];

    /**
     * @param  array<string, mixed>  $data  the validated applicant payload
     */
    public function sync(Applicant $applicant, array $data): void
    {
        $service = $data['service'] ?? $applicant->service;
        if (! BuiltInService::isValid($service)) {
            $service = BuiltInService::SCHOLARSHIP;
        }

        if ($applicant->service !== $service) {
            $applicant->service = $service;
            $applicant->save();
        }

        $attributes = [];
        foreach (self::ATTRIBUTES[$service] as $key) {
            if (array_key_exists($key, $data)) {
                $attributes[$key] = $data[$key];
            }
        }

        if ($attributes !== []) {
            // Write through the concrete relation: a single attribute-driven
            // `details()` relation cannot be eager-loaded reliably.
            $applicant->{$applicant->detailRelationName()}()->updateOrCreate([], $attributes);
        }

        // An explicit empty array clears the selection; an absent key leaves it
        // untouched so a partial update cannot silently wipe stored subjects.
        match ($service) {
            BuiltInService::SCHOLARSHIP, BuiltInService::PRIVATE => $this->syncPivot($applicant, 'favoriteSubjects', $data, 'favorite_subject_ids'),
            BuiltInService::TEST_OFFER => $this->syncPivot($applicant, 'skills', $data, 'skill_ids'),
            BuiltInService::ACADEMY => $this->syncPivot($applicant, 'teachers', $data, 'teacher_ids'),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncPivot(Applicant $applicant, string $relation, array $data, string $key): void
    {
        if (! array_key_exists($key, $data)) {
            return;
        }

        $applicant->{$relation}()->sync(array_map('intval', (array) $data[$key]));
    }
}
