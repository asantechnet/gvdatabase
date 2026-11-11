<?php

namespace App\Support;

use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Scopes contracts to the actor's contract category and employee group.
 *
 * A category is one of the four built-in services, employment, or other
 * (including administrator-defined non-system contract types). The category
 * permission is an access gate; the existing create/edit/cancel/delete
 * permissions remain the action gates.
 *
 * A contract is owned by the employee it is drawn for, the employee who sold
 * the service, or the responsible employee of its applicant. A contract with
 * no owner at all is organisational (company/other contracts) and stays
 * visible to anyone who may read that category.
 */
class ContractAccess
{
    public static function categoryPermission(string $category): string
    {
        return match ($category) {
            'employment' => 'view_employment_contracts',
            'other' => 'view_other_contracts',
            default => BuiltInService::contractPermission($category) ?? 'view_other_contracts',
        };
    }

    public static function categoryFor(Contract $contract): string
    {
        if (BuiltInService::isValid($contract->service)) {
            return $contract->service;
        }

        return $contract->contractType?->slug === 'employment' ? 'employment' : 'other';
    }

    public static function categoryForTemplate(ContractTemplate $template): string
    {
        if (BuiltInService::isValid($template->service)) {
            return $template->service;
        }

        return $template->contractType?->slug === 'employment' ? 'employment' : 'other';
    }

    public static function canAccessCategory(User $user, string $category): bool
    {
        return $user->isSuperAdmin()
            || $user->hasPermission(self::categoryPermission($category));
    }

    public static function canAccessService(User $user, string $service): bool
    {
        return self::canAccessCategory($user, $service);
    }

    public static function canAccessTemplate(User $user, ContractTemplate $template): bool
    {
        return self::canAccessCategory($user, self::categoryForTemplate($template));
    }

    public static function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        self::scopeByCategory($query, $user);

        if ($user->hasPermission('view_all_contracts')) {
            return $query;
        }

        $employeeIds = app(EmployeeScope::class)->visibleEmployeeIds($user);

        return $query->where(function (Builder $query) use ($employeeIds): void {
            $query->where(function (Builder $owned) use ($employeeIds): void {
                $owned->whereIn('employee_id', $employeeIds)
                    ->orWhereIn('sold_by_employee_id', $employeeIds)
                    ->orWhereHas('applicant', fn ($applicant) => $applicant
                        ->where(fn ($assigned) => $assigned
                            ->whereIn('responsible_employee_id', $employeeIds)
                            ->orWhereHas('responsibleEmployees', fn ($employees) => $employees->whereIn('employees.id', $employeeIds))));
            })->orWhere(function (Builder $ownerless): void {
                $ownerless->whereNull('employee_id')
                    ->whereNull('sold_by_employee_id')
                    ->where(fn (Builder $inner) => $inner
                        ->whereNull('applicant_id')
                        ->orWhereHas('applicant', fn ($applicant) => $applicant
                            ->whereNull('responsible_employee_id')
                            ->whereDoesntHave('responsibleEmployees')));
            });
        });
    }

    public static function canView(User $user, Contract $contract): bool
    {
        if (! self::canAccessCategory($user, self::categoryFor($contract))) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->hasPermission('view_all_contracts')) {
            return true;
        }

        return self::scopeVisibleTo(Contract::query()->whereKey($contract->id), $user)->exists();
    }

    public static function canManage(User $user, Contract $contract): bool
    {
        if (! self::canAccessCategory($user, self::categoryFor($contract))) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->hasPermission('view_all_contracts')) {
            return true;
        }

        $ownerIds = array_values(array_filter([
            $contract->employee_id,
            $contract->sold_by_employee_id,
            $contract->applicant?->responsible_employee_id,
        ], fn ($id) => $id !== null));

        if ($ownerIds === []) {
            return true;
        }

        $scope = app(EmployeeScope::class);
        foreach ($ownerIds as $ownerId) {
            if ($scope->canManageOwnedBy($user, (int) $ownerId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Keep only the categories the actor may read. The ownership scope is
     * applied separately by `scopeVisibleTo`.
     */
    private static function scopeByCategory(Builder $query, User $user): void
    {
        $query->where(function (Builder $scope) use ($user): void {
            $matched = false;

            foreach (BuiltInService::ALL as $service) {
                if (self::canAccessCategory($user, $service)) {
                    $scope->orWhere('service', $service);
                    $matched = true;
                }
            }

            if (self::canAccessCategory($user, 'employment')) {
                $scope->orWhere(fn (Builder $employment) => $employment
                    ->whereNull('service')
                    ->whereHas('contractType', fn ($type) => $type->where('slug', 'employment')));
                $matched = true;
            }

            if (self::canAccessCategory($user, 'other')) {
                $scope->orWhere(fn (Builder $other) => $other
                    ->whereNull('service')
                    ->whereHas('contractType', fn ($type) => $type
                        ->where('slug', 'other')
                        ->orWhere('is_system', false)));
                $matched = true;
            }

            if (! $matched) {
                $scope->whereRaw('1 = 0');
            }
        });
    }
}
