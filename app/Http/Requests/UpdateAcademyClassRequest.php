<?php

namespace App\Http\Requests;

use App\Models\AcademyClass;
use Illuminate\Validation\Validator;

class UpdateAcademyClassRequest extends StoreAcademyClassRequest
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $class = $this->route('academyClass');
            $capacity = $this->input('capacity');

            if (! $class instanceof AcademyClass || $capacity === null || $capacity === '') {
                return;
            }

            $active = $class->enrollments()->where('status', 'active')->count();
            if ((int) $capacity < $active) {
                $validator->errors()->add('capacity', 'business.academy_class_capacity_below_enrolled');
            }
        });
    }
}
