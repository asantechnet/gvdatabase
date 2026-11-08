<?php

namespace App\Http\Requests;

use App\Models\AcademyAttendance;
use App\Models\AcademyClass;
use App\Models\AcademyEnrollment;
use App\Models\AcademyTeacherTimesheet;
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
        $isTeacher = $this->has('teacher_rows');
        $time = ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/'];

        if ($isTeacher) {
            return [
                'teacher_rows' => ['required', 'array', 'min:1', 'max:2000'],
                'teacher_rows.*.teacher_id' => ['required', 'integer', Rule::exists('teachers', 'id')->whereNull('deleted_at')],
                'teacher_rows.*.class_id' => ['required', 'integer', Rule::exists('academy_classes', 'id')->whereNull('deleted_at')],
                'teacher_rows.*.date' => ['required', 'date_format:Y-m-d'],
                'teacher_rows.*.status' => ['required', Rule::in(AcademyTeacherTimesheet::STATUSES)],
                'teacher_rows.*.starts_at' => $time,
                'teacher_rows.*.ends_at' => $time,
                'teacher_rows.*.minutes' => ['nullable', 'integer', 'between:0,1440'],
                'teacher_rows.*.note' => ['nullable', 'string', 'max:500'],
            ];
        }

        return [
            'rows' => ['required', 'array', 'min:1', 'max:2000'],
            'rows.*.enrollment_id' => ['required', 'integer', Rule::exists('academy_enrollments', 'id')->whereNull('deleted_at')],
            'rows.*.date' => ['required', 'date_format:Y-m-d'],
            'rows.*.status' => ['required', Rule::in(AcademyAttendance::STATUSES)],
            'rows.*.minutes' => ['nullable', 'integer', 'between:0,1440'],
            'rows.*.note' => ['nullable', 'string', 'max:500'],
            'rows.*.override' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->has('teacher_rows')) {
                $this->validateTeacherRows($validator);

                return;
            }

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
                $override = (bool) ($row['override'] ?? false);

                if ((! $within || ! $meets) && ! $override) {
                    $validator->errors()->add("rows.$index.date", 'This date is outside the enrollment term or class weekday.');
                }
                if ((! $within || ! $meets) && $override && blank($row['note'] ?? null)) {
                    $validator->errors()->add("rows.$index.note", 'An override needs a note.');
                }
            }
        });
    }

    private function validateTeacherRows(Validator $validator): void
    {
        $seen = [];
        foreach ($this->input('teacher_rows', []) as $index => $row) {
            $teacherId = (int) ($row['teacher_id'] ?? 0);
            $classId = (int) ($row['class_id'] ?? 0);
            $date = (string) ($row['date'] ?? '');
            $key = $teacherId.'|'.$classId.'|'.$date;
            if (isset($seen[$key])) {
                $validator->errors()->add("teacher_rows.$index.date", 'This teacher/class/date row is duplicated.');
            }
            $seen[$key] = true;

            if (! AcademyClass::query()->whereKey($classId)->exists()) {
                $validator->errors()->add("teacher_rows.$index.class_id", 'The selected class does not exist.');
            }

            if (! empty($row['starts_at']) && ! empty($row['ends_at']) && $row['ends_at'] < $row['starts_at']) {
                $validator->errors()->add("teacher_rows.$index.ends_at", 'The end time must not be before the start time.');
            }
        }
    }
}
