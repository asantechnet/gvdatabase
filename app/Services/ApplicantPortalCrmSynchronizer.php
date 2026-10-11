<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the applicant record fresh for a portal account.
 *
 * This used to create a separate `Customer`, then a CRM `Lead`; both entities
 * are gone. The applicant *is* the party, so the synchronizer only backfills
 * the absorbed identity fields on the applicant.
 */
class ApplicantPortalCrmSynchronizer
{
    public function synchronize(User $user): void
    {
        if (! in_array('applicant', $user->roleNames(), true)) {
            return;
        }

        DB::transaction(function () use ($user): void {
            $applicant = Applicant::query()->lockForUpdate()->where('user_id', $user->id)->first();
            if (! $applicant) {
                return;
            }

            $this->backfillIdentity($applicant, $user);
        });
    }

    /**
     * The portal account is the authority on the person's own contact details,
     * so fill in whatever the case is still missing. Never overwrite: staff may
     * have corrected a value that the portal does not know about.
     */
    private function backfillIdentity(Applicant $applicant, User $user): void
    {
        $fill = array_filter([
            'name' => $user->name,
            'contact' => $user->phone ?: $user->email,
            'email' => $user->email,
            'address' => $user->address,
        ], fn ($value) => $value !== null && $value !== '');

        $missing = array_filter($fill, fn ($value, $key) => blank($applicant->$key), ARRAY_FILTER_USE_BOTH);
        if ($missing !== []) {
            $applicant->fill($missing)->save();
        }
    }
}
