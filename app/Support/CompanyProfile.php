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
            'name' => $setting->short_company_name ?: $setting->website_title,
        ];
    }

    public function timezone(): string
    {
        $timezone = $this->current()?->timezone;

        return $timezone && in_array($timezone, DateTimeZone::listIdentifiers(), true)
            ? $timezone
            : (string) config('app.timezone', 'UTC');
    }
}
