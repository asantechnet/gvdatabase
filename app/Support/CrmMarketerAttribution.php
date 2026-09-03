<?php

namespace App\Support;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Keeps CRM attribution tied to the employee who owns the relationship.
 *
 * The old `marketer` text column remains as a human-readable compatibility
 * label while `marketer_id` is the authoritative, relational value.
 */
final class CrmMarketerAttribution
{
    /**
     * Validation rules for an explicit CRM marketer selection.
     *
     * @return array<int, mixed>
     */
    public static function marketerIdRules(): array
    {
        return [
            'nullable',
            'integer',
            Rule::exists('employees', 'id')->whereNull('deleted_at'),
            function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value !== null && ! self::findMarketerEmployee((int) $value)) {
                    $fail('The selected marketer must be an active employee in the Marketer position.');
                }
            },
        ];
    }

    /**
     * Apply the current marketer's ownership or synchronize an explicit
     * administrator selection to its legacy display label.
     *
     * On creation, a marketer always owns the record. On update, ownership is
     * protected only when the request attempts to change marketer data, so a
     * normal edit cannot silently reassign an existing relationship.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function apply(Request $request, array $attributes, bool $creating): array
    {
        $actor = self::actingMarketerEmployee($request);
        $isChangingAttribution = array_key_exists('marketer_id', $attributes)
            || array_key_exists('marketer', $attributes);

        if ($actor && ($creating || $isChangingAttribution)) {
            return self::forEmployee($attributes, $actor);
        }

        if (! array_key_exists('marketer_id', $attributes) && ! array_key_exists('marketer', $attributes)) {
            return $attributes;
        }

        // Transitional support for the existing name-based CRM forms. An
        // unambiguous current marketer name is upgraded to the relational ID;
        // a legacy/free-form label remains visible but cannot leave a stale ID
        // pointing at a different employee.
        if (! array_key_exists('marketer_id', $attributes)) {
            $legacyLabel = trim((string) ($attributes['marketer'] ?? ''));

            if ($legacyLabel === '') {
                $attributes['marketer'] = null;
                $attributes['marketer_id'] = null;

                return $attributes;
            }

            $marketer = self::findMarketerEmployeeByName($legacyLabel);

            if ($marketer) {
                return self::forEmployee($attributes, $marketer);
            }

            $attributes['marketer_id'] = null;

            return $attributes;
        }

        if ($attributes['marketer_id'] === null) {
            // A cleared relation must not leave a misleading legacy label.
            $attributes['marketer'] = null;

            return $attributes;
        }

        $marketer = self::findMarketerEmployee((int) $attributes['marketer_id']);

        return $marketer ? self::forEmployee($attributes, $marketer) : $attributes;
    }

    /** Resolve the linked employee only when the authenticated user is a marketer. */
    public static function actingMarketerEmployee(Request $request): ?Employee
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        return Employee::query()
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->whereHas('position', fn ($query) => $query->where('system_key', 'marketer'))
            ->first();
    }

    /** @param array<string, mixed> $attributes */
    private static function forEmployee(array $attributes, Employee $employee): array
    {
        $attributes['marketer_id'] = $employee->id;
        $attributes['marketer'] = $employee->name;

        return $attributes;
    }

    private static function findMarketerEmployee(int $employeeId): ?Employee
    {
        return Employee::query()
            ->whereKey($employeeId)
            ->whereNull('deleted_at')
            ->whereHas('position', fn ($query) => $query->where('system_key', 'marketer'))
            ->first();
    }

    private static function findMarketerEmployeeByName(string $name): ?Employee
    {
        $marketers = Employee::query()
            ->where('name', $name)
            ->whereNull('deleted_at')
            ->whereHas('position', fn ($query) => $query->where('system_key', 'marketer'))
            ->limit(2)
            ->get();

        return $marketers->count() === 1 ? $marketers->first() : null;
    }
}
