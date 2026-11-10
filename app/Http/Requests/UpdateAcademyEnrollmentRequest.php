<?php

namespace App\Http\Requests;

use App\Models\AcademyEnrollment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAcademyEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('manage_academy_classes') ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(AcademyEnrollment::TERMINAL_STATUSES)],
            'completed_on' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
