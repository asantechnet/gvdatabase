<?php

namespace App\Support;

use App\Models\Setting;
use DateTimeZone;
use Illuminate\Support\Facades\Schema;

class CompanyProfile
{
    public function current(): ?Setting
    {
        if (! Schema::hasTable('settings')) {
            return null;
        }

        return Setting::query()->latest('id')->first();
    }

    /** @return array{address: string, name: string}|null */
    public function mailFrom(): ?array
    {
        $setting = $this->current();
        if (! $setting?->company_email) {
            return null;
        }

        return [
            'address' => $setting->company_email,
            'name' => $setting->mail_from_name ?: $setting->short_company_name ?: $setting->website_title,
        ];
    }

    /** Configure and return the company SMTP mailer, or null when .env should be used. */
    public function configureMailer(): ?string
    {
        $setting = $this->current();

        if (! $setting?->mail_host || ! $setting->mail_port || ! $setting->mail_username || ! $setting->mail_password) {
            return null;
        }

        $mailer = 'company_smtp';
        config(["mail.mailers.{$mailer}" => [
            'transport' => 'smtp',
            'host' => $setting->mail_host,
            'port' => (int) $setting->mail_port,
            'scheme' => $setting->mail_encryption,
            'username' => $setting->mail_username,
            'password' => $setting->mail_password,
            'timeout' => null,
            'local_domain' => null,
        ]]);

        app('mail.manager')->purge($mailer);

        return $mailer;
    }

    public function replyTo(): ?string
    {
        return $this->current()?->mail_reply_to;
    }

    public function timezone(): string
    {
        $timezone = $this->current()?->timezone;

        return $timezone && in_array($timezone, DateTimeZone::listIdentifiers(), true)
            ? $timezone
            : (string) config('app.timezone', 'UTC');
    }

    /**
     * Feature gate for starting self-service workdays and automatic
     * creation of missing attendance rows. Manual entries are still calculated
     * and summarized. Existing workdays may still be ended when this is off.
     */
    public function automaticAttendanceEnabled(): bool
    {
        return $this->current()?->automatic_attendance_enabled ?? false;
    }
}
