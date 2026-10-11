<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An applicant now belongs to exactly one built-in service, and each service
 * asks for a different set of fields.
 *
 * `applicants` keeps the shared identity (name, guardian, contact, address,
 * marketer, portal user, employee, stage), gains `service`, and the per-service
 * data moves into three 1:1 detail tables:
 *
 *   - scholarship_applicant_details  (passport, grade, country, contract type,
 *     education level, semester, university, university fee)
 *   - test_applicant_details         (passport, test)
 *   - academy_applicant_details      (program, class time, duration)
 *
 * Multi-valued fields use real pivots (favourite subjects, skills, teachers).
 * Money is deliberately absent: the applicant's price, currency, rate,
 * discount, paid amount and payment status live on the `applicant_contract`
 * line so one place owns them. Notes stay on `applicants.notes` so there is one
 * note field rather than one per service.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->string('service', 24)->default('scholarship')->index()->after('id');
        });

        Schema::create('scholarship_applicant_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('passport_id', 64)->nullable();
            $table->decimal('grade_score', 8, 2)->nullable();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contract_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('education_level_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('university_id')->nullable()->constrained()->nullOnDelete();
            // Reference cost quoted by the university — not the firm's price.
            $table->decimal('university_fee', 20, 6)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('test_applicant_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('passport_id', 64)->nullable();
            $table->foreignId('test_id')->nullable()->constrained('tests')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('academy_applicant_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('time_id')->nullable()->constrained('times')->nullOnDelete();
            $table->foreignId('duration_id')->nullable()->constrained('durations')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('scholarship_applicant_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['applicant_id', 'subject_id']);
        });

        Schema::create('test_applicant_skill', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['applicant_id', 'skill_id']);
        });

        Schema::create('academy_applicant_teacher', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['applicant_id', 'teacher_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academy_applicant_teacher');
        Schema::dropIfExists('test_applicant_skill');
        Schema::dropIfExists('scholarship_applicant_subject');
        Schema::dropIfExists('academy_applicant_details');
        Schema::dropIfExists('test_applicant_details');
        Schema::dropIfExists('scholarship_applicant_details');

        Schema::table('applicants', function (Blueprint $table) {
            $table->dropIndex(['service']);
            $table->dropColumn('service');
        });
    }
};
