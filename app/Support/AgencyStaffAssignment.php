<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\Position;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Optional specialist assignments, independent of case ownership. */
final class AgencyStaffAssignment
{
    public static function query(string $specialty): Builder
    {
        // The plural responsible-employee picker is driven by the configured
        // HR positions. A null setting keeps the historical admission default;
        // an explicitly empty list means nobody is currently eligible.
        if ($specialty !== 'counselor') {
            return Employee::query()
                ->active()
                ->whereIn('position_id', self::responsiblePositionIds())
                ->whereHas('position', fn (Builder $position) => $position->where('status', 'active'));
        }

        $names = ['counselor', 'senior_counselor'];

        return Employee::query()->active()->where(function (Builder $query) use ($names) {
            $query->whereHas('position', fn (Builder $position) => $position
                ->whereIn('system_key', $names)
                ->orWhereIn(DB::raw('LOWER(title)'), array_map(fn ($name) => str_replace('_', ' ', $name), $names)))
                ->orWhereHas('user.roles', fn (Builder $role) => $role->whereIn('name', $names));
        });
    }

    /** @return array<int, int> */
    public static function responsiblePositionIds(): array
    {
        $setting = Setting::query()->latest('id')->first();
        $configured = $setting?->responsible_position_ids;

        if ($configured === null) {
            return self::defaultResponsiblePositionIds();
        }

        return collect($configured)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<int, int> */
    public static function defaultResponsiblePositionIds(): array
    {
        $names = ['admission', 'admission_officer'];

        return Position::query()
            ->where('status', 'active')
            ->where(function (Builder $query) use ($names) {
                $query->whereIn('system_key', $names)
                    ->orWhereIn(DB::raw('LOWER(title)'), array_map(fn ($name) => str_replace('_', ' ', $name), $names));
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public static function rules(string $specialty): array
    {
        return ['nullable', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at'),
            function (string $attribute, mixed $value, \Closure $fail) use ($specialty): void {
                if ($value !== null && ! self::query($specialty)->whereKey($value)->exists()) {
                    $fail("Choose an active employee in a position configured for {$specialty} assignments.");
                }
            },
        ];
    }
}
