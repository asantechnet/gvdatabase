<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employment templates are not versioned per country/language: their versions
 * are identified by name only. `language_id` therefore has to be nullable.
 *
 * Note: MySQL treats NULL as distinct inside a composite unique index, so the
 * (template, country, test, language, version) unique index cannot enforce
 * uniqueness for a fully-null scope. Uniqueness for employment versions is
 * enforced by the `current_scope_key` unique index (one current version per
 * template) plus the transactional version-number allocation in
 * ContractTemplateVersionController.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_template_versions', function (Blueprint $table) {
            $table->foreignId('language_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('contract_template_versions', function (Blueprint $table) {
            $table->foreignId('language_id')->nullable(false)->change();
        });
    }
};
