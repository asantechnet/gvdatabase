<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Keeps a traceable CRM lead for an applicant portal account.
 *
 * This used to also create a separate `Customer` and attach it to the case.
 * The applicant now *is* the party, so the synchronizer only backfills the
 * absorbed identity fields on the case and links the lead directly to it.
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

            Lead::firstOrCreate(['applicant_id' => $applicant->id], [
                'applicant_id' => $applicant->id,
                'name' => $user->name,
                'company' => $applicant->company ?: $user->name,
                'contact' => $user->phone ?: $user->email,
                'email' => $user->email,
                'service_interest' => 'Educational application services',
                'stage' => 'qualified',
                'probability' => 50,
                'probability_is_manual' => false,
                'notes' => 'Created automatically when the applicant signed in to the portal.',
                'owner_id' => $user->id,
            ]);
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
            'company' => $user->name,
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
