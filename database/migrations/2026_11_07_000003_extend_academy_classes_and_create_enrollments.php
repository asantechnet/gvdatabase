<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Academy operational foundation.
 *
 * A student is a durable Academy person record. It is deliberately separate
 * from an applicant (the contract/case party) and from an enrollment (one
 * course taken by one student). The legacy academy detail keeps its student
 * number; the backfill copies that number into `students`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            // The number printed on the old academy detail. Duplicate values
            // are retained as a data-quality finding and are never merged.
            $table->string('student_number', 64)->nullable()->index();
            $table->foreignId('applicant_id')->nullable()->constrained('applicants')->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('father_name')->nullable();
            $table->string('father_phone', 50)->nullable();
            $table->string('contact', 50)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('applicant_id');
        });

        Schema::table('durations', function (Blueprint $table) {
            $table->unsignedSmallInteger('months')->nullable()->after('name');
        });

        Schema::table('academy_classes', function (Blueprint $table) {
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('time_id')->nullable()->constrained('times')->nullOnDelete();
            $table->foreignId('duration_id')->nullable()->constrained('durations')->nullOnDelete();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->json('weekdays')->nullable();
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->decimal('fee', 20, 2)->nullable();

            $table->index(['is_active', 'starts_on', 'ends_on']);
        });

        Schema::create('academy_class_teacher', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academy_class_id')->constrained('academy_classes')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['academy_class_id', 'teacher_id']);
        });

        Schema::create('academy_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('applicant_id')->nullable()->constrained('applicants')->nullOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->foreignId('applicant_contract_id')->nullable()->constrained('applicant_contract')->nullOnDelete();
            $table->foreignId('class_id')->constrained('academy_classes')->restrictOnDelete();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('time_id')->nullable()->constrained('times')->nullOnDelete();
            $table->foreignId('duration_id')->nullable()->constrained('durations')->nullOnDelete();
            $table->date('started_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status', 20)->default('active');
            $table->date('completed_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('applicant_contract_id');
            $table->index(['class_id', 'status']);
            $table->index(['applicant_id', 'status']);
            $table->index(['status', 'ends_on']);
            $table->index(['status', 'completed_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academy_enrollments');
        Schema::dropIfExists('academy_class_teacher');

        Schema::table('academy_classes', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'starts_on', 'ends_on']);
            $table->dropConstrainedForeignId('program_id');
            $table->dropConstrainedForeignId('time_id');
            $table->dropConstrainedForeignId('duration_id');
            $table->dropColumn(['starts_on', 'ends_on', 'weekdays', 'starts_at', 'ends_at', 'capacity', 'fee']);
        });

        Schema::table('durations', function (Blueprint $table) {
            $table->dropColumn('months');
        });

        Schema::dropIfExists('students');
    }
};
