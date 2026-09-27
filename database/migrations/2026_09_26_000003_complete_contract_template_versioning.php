<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contract_template_versions', function (Blueprint $table) {
            $table->dropUnique('ctv_template_language_version_unique');
            $table->string('name')->default('Untitled version')->after('version_number');
            // Set only on the active row.  A normal unique index is portable
            // across MySQL and SQLite and therefore enforces one active row
            // per template/country/language scope without database-specific
            // partial-index SQL.
            $table->string('current_scope_key', 100)->nullable()->unique()->after('is_current');
            $table->foreignId('activated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable()->after('activated_by');
            $table->foreignId('archived_by')->nullable()->after('activated_at')->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable()->after('archived_by');
            $table->unique(['contract_template_id', 'country_id', 'language_id', 'version_number'], 'ctv_template_country_language_version_unique');
        });

        // Repair any legacy duplicate-current rows deterministically before
        // the portable unique key starts enforcing the invariant.
        DB::table('contract_template_versions')->where('is_current', true)->orderByDesc('version_number')->orderByDesc('id')->get()
            ->groupBy(fn (object $version) => implode(':', [$version->contract_template_id, $version->country_id, $version->language_id]))
            ->each(function ($versions, string $scope): void {
                $winner = $versions->shift();
                DB::table('contract_template_versions')->where('id', $winner->id)->update(['current_scope_key' => $scope]);
                DB::table('contract_template_versions')->whereIn('id', $versions->pluck('id'))->update(['is_current' => false, 'current_scope_key' => null]);
            });

        Schema::table('contracts', function (Blueprint $table) {
            $table->foreignId('contract_country_id')->nullable()->after('contract_template_version_id')->constrained('countries')->nullOnDelete();
            $table->foreignId('contract_language_id')->nullable()->after('contract_country_id')->constrained('languages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contract_language_id');
            $table->dropConstrainedForeignId('contract_country_id');
        });
        Schema::table('contract_template_versions', function (Blueprint $table) {
            $table->dropUnique('ctv_template_country_language_version_unique');
            $table->dropUnique(['current_scope_key']);
            $table->dropConstrainedForeignId('archived_by');
            $table->dropColumn('archived_at');
            $table->dropConstrainedForeignId('activated_by');
            $table->dropColumn('activated_at');
            $table->dropColumn('current_scope_key');
            $table->dropColumn('name');
        });
    }
};
