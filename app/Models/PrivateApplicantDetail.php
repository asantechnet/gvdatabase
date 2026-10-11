<?php

namespace App\Models;

/** Private admissions share academic fields with scholarship admissions. */
class PrivateApplicantDetail extends ScholarshipApplicantDetail
{
    protected $table = 'private_applicant_details';

    protected $fillable = ['applicant_id', 'passport_id', 'grade_score', 'country_id', 'education_level_id', 'semester_id', 'university_id', 'contract_type_id', 'university_fee'];
}
