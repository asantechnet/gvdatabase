<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\Role;
use App\Models\User;
use App\Support\CompanyProfile;
use Illuminate\Support\Str;

/**
 * Owns the applicant's portal login: the one account behind `applicants.user_id`.
 *
 * Accounts are always born verified and with the `applicant` role only, so a
 * staff-issued password signs in immediately without an email round trip.
 */
class ApplicantPortalAccountService
{
    public function __construct(private readonly CompanyProfile $company) {}

    /**
     * A free login email plus a strong password for an account not created yet.
     *
     * @return array{email: string, password: string}
     */
    public function suggest(?string $name, ?string $email, ?string $education): array
    {
        return [
            'email' => $this->freeEmail($name, $email, $education),
            'password' => Str::password(14),
        ];
    }

    /**
     * Create the account with exactly the supplied credentials.
     *
     * A null password keeps the invitation contract: the account is unusable
     * until the applicant verifies the address and picks their own password.
     *
     * @param  array<string, mixed>  $profile
     */
    public function create(Applicant $applicant, string $name, string $email, ?string $password, array $profile = []): User
    {
        abort_if($applicant->user_id, 422, 'business.applicant_portal_account_exists');
        $role = Role::query()->where('name', 'applicant')->firstOrFail();
        $invited = $password === null;
        $user = User::create([
            'name' => $name,
            'email' => Str::lower($email),
            'phone' => $profile['phone'] ?? null,
            'address' => $profile['address'] ?? null,
            'password' => $password ?? Str::random(64),
            'acc_status' => 'active',
            'must_set_password' => $invited,
            'email_verified_at' => $invited ? null : now(),
        ]);
        $user->roles()->sync([$role->id]);
        $applicant->update(['user_id' => $user->id]);

        return $user;
    }

    /** Remove the login, its sessions and the case link, keeping the case itself. */
    public function delete(Applicant $applicant): void
    {
        $user = $applicant->user;
        abort_unless($user, 422, 'business.applicant_portal_account_missing');
        $applicant->update(['user_id' => null]);
        $user->tokens()->delete();
        $user->delete();
    }

    /** The applicant's own address when it is free, otherwise a readable generated one. */
    private function freeEmail(?string $name, ?string $email, ?string $education): string
    {
        if (filled($email) && filter_var($email, FILTER_VALIDATE_EMAIL) && $this->isFree($email)) {
            return Str::lower($email);
        }

        $base = Str::slug((string) $name) ?: 'applicant';
        $study = Str::slug((string) $education);
        if ($study !== '') {
            $base .= ".{$study}";
        }
        $domain = $this->domain();

        do {
            $candidate = "{$base}.".random_int(1000, 999999)."@{$domain}";
        } while (! $this->isFree($candidate));

        return $candidate;
    }

    /** Soft-deleted accounts still own their address, because the column is unique. */
    private function isFree(string $email): bool
    {
        return ! User::withTrashed()->whereRaw('LOWER(email) = ?', [Str::lower($email)])->exists();
    }

    /** Generated logins borrow the firm's own domain; example.com stands in until one is set. */
    private function domain(): string
    {
        $companyEmail = (string) $this->company->current()?->company_email;
        $domain = Str::lower(Str::after($companyEmail, '@'));

        return str_contains($companyEmail, '@') && $domain !== '' ? $domain : 'example.com';
    }
}
