<?php

use App\Support\ThemePalette;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('success_color')->default(ThemePalette::DEFAULTS['success_color'])->after('secondary_color');
            $table->string('info_color')->default(ThemePalette::DEFAULTS['info_color'])->after('success_color');
            $table->string('warning_color')->default(ThemePalette::DEFAULTS['warning_color'])->after('info_color');
            $table->string('error_color')->default(ThemePalette::DEFAULTS['error_color'])->after('warning_color');
            $table->string('light_background_color')->default(ThemePalette::DEFAULTS['light_background_color'])->after('error_color');
            $table->string('light_surface_color')->default(ThemePalette::DEFAULTS['light_surface_color'])->after('light_background_color');
            $table->string('dark_background_color')->default(ThemePalette::DEFAULTS['dark_background_color'])->after('light_surface_color');
            $table->string('dark_surface_color')->default(ThemePalette::DEFAULTS['dark_surface_color'])->after('dark_background_color');
        });

        // Existing primary and secondary overrides remain intact. Missing values
        // become the Vuexy defaults so the API always returns a full palette.
        foreach (['primary_color', 'secondary_color'] as $field) {
            DB::table('settings')
                ->whereNull($field)
                ->update([$field => ThemePalette::DEFAULTS[$field]]);
        }

        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('tertiary_color');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('tertiary_color')->nullable()->after('secondary_color');
            $table->dropColumn([
                'success_color',
                'info_color',
                'warning_color',
                'error_color',
                'light_background_color',
                'light_surface_color',
                'dark_background_color',
                'dark_surface_color',
            ]);
        });
    }
};
