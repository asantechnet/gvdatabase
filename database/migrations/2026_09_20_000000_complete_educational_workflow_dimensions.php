<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('semesters', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 30)->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::table('applicants', function (Blueprint $table) {
            $table->foreignId('semester_id')->nullable()->after('language_id')->constrained()->nullOnDelete();
            $table->index('semester_id');
        });
        Schema::table('applicant_universities', function (Blueprint $table) {
            $table->foreignId('semester_id')->nullable()->after('university_id')->constrained()->nullOnDelete();
        });
        Schema::table('applicant_stages', function (Blueprint $table) {
            $table->foreignId('country_id')->nullable()->after('key')->constrained()->nullOnDelete();
            $table->foreignId('university_id')->nullable()->after('country_id')->constrained()->nullOnDelete();
            $table->index(['country_id', 'university_id', 'is_active'], 'applicant_stage_context_index');
        });
        Schema::table('contract_template_versions', function (Blueprint $table) {
            $table->foreignId('country_id')->nullable()->after('language_id')->constrained()->nullOnDelete();
            $table->index(['contract_template_id', 'country_id', 'language_id', 'is_current'], 'ctv_country_language_current_index');
        });
    }

    public function down(): void
    {
        Schema::table('contract_template_versions', function (Blueprint $table) { $table->dropIndex('ctv_country_language_current_index'); $table->dropConstrainedForeignId('country_id'); });
        Schema::table('applicant_stages', function (Blueprint $table) { $table->dropIndex('applicant_stage_context_index'); $table->dropConstrainedForeignId('university_id'); $table->dropConstrainedForeignId('country_id'); });
        Schema::table('applicant_universities', function (Blueprint $table) { $table->dropConstrainedForeignId('semester_id'); });
        Schema::table('applicants', function (Blueprint $table) { $table->dropIndex(['semester_id']); $table->dropConstrainedForeignId('semester_id'); });
        Schema::dropIfExists('semesters');
    }
};
