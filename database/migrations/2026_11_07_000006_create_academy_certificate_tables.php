<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academy_certificate_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('title');
            $table->text('body');
            $table->string('signatory_title')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('academy_certificates', function (Blueprint $table) {
            $table->id();
            $table->string('certificate_number')->nullable()->unique();
            $table->foreignId('academy_enrollment_id')->constrained('academy_enrollments')->restrictOnDelete();
            $table->foreignId('applicant_id')->nullable()->constrained('applicants')->nullOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('academy_classes')->nullOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->foreignId('applicant_contract_id')->nullable()->constrained('applicant_contract')->nullOnDelete();
            $table->foreignId('academy_certificate_template_id')->nullable()->constrained('academy_certificate_templates')->nullOnDelete();
            $table->timestamp('issued_at');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('snapshot');
            $table->string('status', 20)->default('issued');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('revocation_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['academy_enrollment_id']);
            $table->index(['status', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academy_certificates');
        Schema::dropIfExists('academy_certificate_templates');
    }
};
