<?php

namespace Tests;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;

abstract class AcademyTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('settings')) {
            return;
        }

        $setting = Setting::query()->first();

        if ($setting) {
            $setting->update(['academy_enabled' => true]);

            return;
        }

        Setting::create([
            'academy_enabled' => true,
            'website_title' => 'Academy Test Company',
            'phone' => '0000000000',
            'physical_address' => 'Test address',
            'short_company_name' => 'Academy Test',
            'original_company_name' => 'Academy Test Company',
        ]);
    }
}
