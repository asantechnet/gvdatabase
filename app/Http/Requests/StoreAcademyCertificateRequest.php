<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAcademyCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('issue_academy_certificates') ?? false;
    }

    public function rules(): array
    {
        $bulk = $this->filled('class_id');

        return [
            'academy_enrollment_id' => $bulk ? ['prohibited'] : ['required', 'integer', Rule::exists('academy_enrollments', 'id')->whereNull('deleted_at')],
            'class_id' => $bulk ? ['required', 'integer', Rule::exists('academy_classes', 'id')->whereNull('deleted_at')] : ['prohibited'],
            'enrollment_ids' => $bulk ? ['required', 'array', 'min:1'] : ['prohibited'],
            'enrollment_ids.*' => ['integer', Rule::exists('academy_enrollments', 'id')->whereNull('deleted_at')],
            'template_id' => ['nullable', 'integer', Rule::exists('academy_certificate_templates', 'id')->where('is_active', true)],
        ];
    }
}
