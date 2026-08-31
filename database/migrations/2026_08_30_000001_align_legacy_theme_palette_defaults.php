<?php

use App\Support\ThemePalette;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Replace only the old template defaults. Deliberately chosen company
     * branding remains untouched.
     */
    public function up(): void
    {
        $legacyDefaults = [
            'primary_color' => '#7367F0',
            'secondary_color' => '#808390',
            'success_color' => '#28C76F',
            'info_color' => '#00BAD1',
            'warning_color' => '#FF9F43',
            'error_color' => '#FF4C51',
            'light_background_color' => '#F8F7FA',
            'light_surface_color' => '#FFFFFF',
            'dark_background_color' => '#25293C',
            'dark_surface_color' => '#2F3349',
        ];

        foreach ($legacyDefaults as $field => $legacyValue) {
            DB::table('settings')
                ->where($field, $legacyValue)
                ->update([$field => ThemePalette::DEFAULTS[$field]]);
        }
    }

    public function down(): void
    {
        // Palette defaults are presentation data; rolling back must not erase
        // the company's currently selected colors.
    }
};
