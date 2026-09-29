<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Applicant section: the merged party+case entity, its stage history,
 * university applications and document attachments.
 *
 * `applicants` absorbed the former customers table: it owns both party data
 * (name/contact/email/address/tax number) and case dimensions, and groups
 * sibling cases through `parent_applicant_id`. There is no `company` column
 * and no `customer_id` anywhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('marketer')->nullable();
            $table->foreignId('marketer_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('date')->nullable();
            $table->foreignId('parent_applicant_id')->nullable()->constrained('applicants')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('responsible_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('university_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('education_level_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('language_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('current_stage_id')->nullable()->constrained('applicant_stages')->nullOnDelete();
            $table->date('application_date')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->json('profile_data')->nullable();
            $table->timestamp('profile_completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Legacy index name preserved from the customers era.
            $table->index('status', 'applicants_customer_id_status_index');
            $table->index(['responsible_employee_id', 'current_stage_id'], 'applicants_responsible_employee_id_current_stage_id_index');
            $table->index('status', 'applicants_status_index');
            $table->index('semester_id', 'applicants_semester_id_index');
            $table->index('name', 'applicants_name_idx');
            $table->index('marketer_id', 'applicants_marketer_id_idx');
            $table->index('parent_applicant_id', 'applicants_parent_applicant_id_idx');
            $table->index(['name', 'status'], 'applicants_name_status_idx');
        });

        Schema::create('applicant_stage_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
            $table->foreignId('old_stage_id')->nullable()->constrained('applicant_stages')->nullOnDelete();
            $table->foreignId('new_stage_id')->constrained('applicant_stages')->restrictOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at')->useCurrent()->useCurrentOnUpdate();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['applicant_id', 'changed_at'], 'applicant_stage_histories_applicant_id_changed_at_index');
        });

        Schema::create('applicant_universities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
            $table->foreignId('university_id')->constrained('universities')->restrictOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained('semesters')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->string('intake')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['applicant_id', 'university_id', 'intake'], 'applicant_universities_applicant_id_university_id_intake_unique');
            $table->index(['university_id', 'status'], 'applicant_universities_university_id_status_index');
        });

        Schema::create('applicant_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('label');
            $table->string('category')->nullable();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['applicant_id', 'label'], 'applicant_attachments_applicant_id_label_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_attachments');
        Schema::dropIfExists('applicant_universities');
        Schema::dropIfExists('applicant_stage_histories');
        Schema::dropIfExists('applicants');
    }
};
