<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Creates one traceable CRM customer/lead for an applicant portal account. */
class ApplicantPortalCrmSynchronizer
{
    public function synchronize(User $user): void
    {
        if (! in_array('applicant', $user->roleNames(), true)) return;

        DB::transaction(function () use ($user): void {
            $applicant = Applicant::query()->lockForUpdate()->where('user_id', $user->id)->first();
            if (! $applicant) return;

            $customer = $applicant->customer_id
                ? Customer::query()->lockForUpdate()->find($applicant->customer_id)
                : Customer::query()->lockForUpdate()->where('email', $user->email)->first();
            $customer ??= Customer::create([
                'name' => $user->name,
                'company' => $user->name,
                'contact' => $user->phone ?: $user->email,
                'email' => $user->email,
                'address' => $user->address,
                'date' => now()->toDateString(),
            ]);

            if ($applicant->customer_id !== $customer->id) $applicant->update(['customer_id' => $customer->id]);

            Lead::firstOrCreate(['applicant_id' => $applicant->id], [
                'customer_id' => $customer->id,
                'name' => $user->name,
                'company' => $customer->company,
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
}
