<?php

namespace App\Support;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Optional specialist assignments, independent of case ownership. */
final class AgencyStaffAssignment
{
    public static function query(string $specialty): Builder
    {
        $names = $specialty === 'counselor' ? ['counselor', 'senior_counselor'] : ['admission', 'admission_officer'];

        return Employee::query()->active()->where(function (Builder $query) use ($names) {
            $query->whereHas('position', fn (Builder $position) => $position
                ->whereIn('system_key', $names)
                ->orWhereIn(DB::raw('LOWER(title)'), array_map(fn ($name) => str_replace('_', ' ', $name), $names)))
                ->orWhereHas('user.roles', fn (Builder $role) => $role->whereIn('name', $names));
        });
    }

    public static function rules(string $specialty): array
    {
        return ['nullable', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at'),
            function (string $attribute, mixed $value, \Closure $fail) use ($specialty): void {
                if ($value !== null && ! self::query($specialty)->whereKey($value)->exists()) {
                    $fail("Choose an active employee with the {$specialty} position or role.");
                }
            },
        ];
    }
}
