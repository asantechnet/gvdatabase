<?php

namespace App\Support;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Scopes contracts to the actor's employee group.
 *
 * A contract is owned by the employee it is drawn for, the employee who sold
 * the service, or the responsible employee of its applicant. A contract with
 * no owner at all is organisational (company/other contracts) and stays
 * visible to anyone who may read contracts.
 */
class ContractAccess
{
    public static function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin() || $user->hasPermission('view_all_contracts')) {
            return $query;
        }

        $employeeIds = app(EmployeeScope::class)->visibleEmployeeIds($user);

        return $query->where(function (Builder $query) use ($employeeIds): void {
            $query->where(function (Builder $owned) use ($employeeIds): void {
                $owned->whereIn('employee_id', $employeeIds)
                    ->orWhereIn('sold_by_employee_id', $employeeIds)
                    ->orWhereHas('applicant', fn ($applicant) => $applicant->whereIn('responsible_employee_id', $employeeIds));
            })->orWhere(function (Builder $ownerless): void {
                $ownerless->whereNull('employee_id')
                    ->whereNull('sold_by_employee_id')
                    ->where(fn (Builder $inner) => $inner
                        ->whereNull('applicant_id')
                        ->orWhereHas('applicant', fn ($applicant) => $applicant->whereNull('responsible_employee_id')));
            });
        });
    }

    public static function canView(User $user, Contract $contract): bool
    {
        if ($user->isSuperAdmin() || $user->hasPermission('view_all_contracts')) {
            return true;
        }

        return self::scopeVisibleTo(Contract::query()->whereKey($contract->id), $user)->exists();
    }

    public static function canManage(User $user, Contract $contract): bool
    {
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
}
