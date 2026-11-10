<?php

namespace App\Http\Requests;

use App\Models\AcademyAttendance;
use App\Models\AcademyEnrollment;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAcademyAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('manage_academy_attendance') ?? false;
    }

    public function rules(): array
    {
        $time = ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/'];

        return [
            'rows' => ['required', 'array', 'min:1', 'max:2000'],
            'rows.*.enrollment_id' => ['required', 'integer', Rule::exists('academy_enrollments', 'id')->whereNull('deleted_at')],
            'rows.*.date' => ['required', 'date_format:Y-m-d'],
            'rows.*.status' => ['required', Rule::in(AcademyAttendance::STATUSES)],
            'rows.*.check_in_time' => $time,
            'rows.*.check_out_time' => $time,
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $seen = [];
            foreach ($this->input('rows', []) as $index => $row) {
                $enrollment = AcademyEnrollment::with('academyClass')->find($row['enrollment_id'] ?? 0);
                if (! $enrollment) {
                    continue;
                }

                $date = Carbon::parse($row['date']);
                $key = $enrollment->id.'|'.$date->toDateString();
                if (isset($seen[$key])) {
                    $validator->errors()->add("rows.$index.date", 'This enrollment/date row is duplicated.');
                }
                $seen[$key] = true;

                $class = $enrollment->academyClass;
                $within = (! $enrollment->started_on || $date->toDateString() >= $enrollment->started_on->toDateString())
                    && (! $enrollment->ends_on || $date->toDateString() <= $enrollment->ends_on->toDateString())
                    && (! $class?->starts_on || $date->toDateString() >= $class->starts_on->toDateString())
                    && (! $class?->ends_on || $date->toDateString() <= $class->ends_on->toDateString());
                $meets = $class && in_array($date->isoWeekday(), $class->weekdays ?? [], true);
                if (! $within || ! $meets) {
                    $validator->errors()->add("rows.$index.date", 'This date is outside the enrollment term or class weekday.');
                }
                if (($row['status'] ?? null) !== 'present' && (! empty($row['check_in_time']) || ! empty($row['check_out_time']))) {
                    $validator->errors()->add("rows.$index.check_in_time", 'Absent and leave rows cannot contain attendance times.');
                }
                if (! empty($row['check_in_time']) && ! empty($row['check_out_time']) && $row['check_out_time'] < $row['check_in_time']) {
                    $validator->errors()->add("rows.$index.check_out_time", 'The check-out time must not be before check-in.');
                }
            }
        });
    }

}
