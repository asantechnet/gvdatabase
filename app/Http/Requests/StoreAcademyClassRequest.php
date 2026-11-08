<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAcademyClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('manage_academy_classes') ?? false;
    }

    public function rules(): array
    {
        $exists = fn (string $table) => Rule::exists($table, 'id')->whereNull('deleted_at');
        $dateTime = ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/'];

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('academy_classes', 'name')->ignore($this->route('academyClass')?->id)->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:5000'],
            'program_id' => ['nullable', 'integer', $exists('programs')],
            'time_id' => ['nullable', 'integer', $exists('times')],
            'duration_id' => ['nullable', 'integer', $exists('durations')],
            'teacher_ids' => ['nullable', 'array', 'max:50'],
            'teacher_ids.*' => ['integer', 'distinct', $exists('teachers')],
            'weekdays' => ['nullable', 'array', 'max:7'],
            'weekdays.*' => ['integer', 'distinct', 'between:1,7'],
            'starts_on' => ['nullable', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'starts_at' => $dateTime,
            'ends_at' => $dateTime,
            'capacity' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'fee' => ['nullable', 'numeric', 'min:0', 'max:9999999999999', 'decimal:0,2'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
