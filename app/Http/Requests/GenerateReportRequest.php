<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')],
            'office_id' => ['nullable', 'integer', Rule::exists('offices', 'id')->whereNull('deleted_at')],
            'applicant_id' => ['nullable', 'integer', Rule::exists('applicants', 'id')->whereNull('deleted_at')],
            'currency_id' => ['nullable', 'integer', Rule::exists('currencies', 'id')->whereNull('deleted_at')],
            'board_id' => ['nullable', 'integer', Rule::exists('boards', 'id')->whereNull('deleted_at')],
            'status' => ['nullable', 'string', 'max:50'],
            'contract_type' => ['nullable', 'string', 'max:100'],
            'attendance_state' => ['nullable', Rule::in(['present', 'absent', 'off_day', 'incomplete', 'complete'])],
            'format' => ['nullable', Rule::in(['csv', 'print'])],
        ];
    }
}
