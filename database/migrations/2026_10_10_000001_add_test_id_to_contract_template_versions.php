<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Test-offer contracts have no destination country; their variable dimension is
 * the test the applicant is preparing for. `country_id` stays for scholarship
 * and private versions, `test_id` is used for test-offer versions, and a
 * version is only ever scoped by one of the two.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_template_versions', function (Blueprint $table) {
            $table->foreignId('test_id')->nullable()->after('country_id')->constrained('tests')->nullOnDelete();
        });

        Schema::table('contract_template_versions', function (Blueprint $table) {
            $table->dropUnique('ctv_template_country_language_version_unique');
            $table->dropIndex('ctv_country_language_current_index');
            $table->unique(
                ['contract_template_id', 'country_id', 'test_id', 'language_id', 'version_number'],
                'ctv_template_scope_version_unique',
            );
            $table->index(
                ['contract_template_id', 'test_id', 'language_id', 'is_current'],
                'ctv_test_language_current_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('contract_template_versions', function (Blueprint $table) {
            $table->dropUnique('ctv_template_scope_version_unique');
            $table->dropIndex('ctv_test_language_current_index');
            $table->unique(
                ['contract_template_id', 'country_id', 'language_id', 'version_number'],
                'ctv_template_country_language_version_unique',
            );
            $table->index(
                ['contract_template_id', 'country_id', 'language_id', 'is_current'],
                'ctv_country_language_current_index',
            );
        });

        Schema::table('contract_template_versions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('test_id');
        });
    }
};
