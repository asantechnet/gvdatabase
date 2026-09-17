<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 3)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('education_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 10)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('package_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->string('service_type')->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('universities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->string('city')->nullable();
            $table->string('website')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['name', 'country_id']);
        });

        Schema::create('applicant_stages', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_terminal')->default(false);
            $table->json('allowed_next_keys')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('applicants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('responsible_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('university_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('education_level_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('language_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('current_stage_id')->nullable()->constrained('applicant_stages')->nullOnDelete();
            $table->date('application_date')->nullable();
            $table->string('status')->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['customer_id', 'status']);
            $table->index(['responsible_employee_id', 'current_stage_id']);
        });

        Schema::create('applicant_stage_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('old_stage_id')->nullable()->constrained('applicant_stages')->nullOnDelete();
            $table->foreignId('new_stage_id')->constrained('applicant_stages')->restrictOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['applicant_id', 'changed_at']);
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->foreignId('applicant_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
            $table->index('applicant_id');
        });

        Schema::table('contract_types', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('applicant_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
            $table->index('applicant_id');
        });

        foreach ([
            ['key' => 'new', 'name' => 'New', 'sort_order' => 10, 'next' => ['consultation']],
            ['key' => 'consultation', 'name' => 'Consultation', 'sort_order' => 20, 'next' => ['documents_required']],
            ['key' => 'documents_required', 'name' => 'Documents Required', 'sort_order' => 30, 'next' => ['documents_complete']],
            ['key' => 'documents_complete', 'name' => 'Documents Complete', 'sort_order' => 40, 'next' => ['university_selection']],
            ['key' => 'university_selection', 'name' => 'University Selection', 'sort_order' => 50, 'next' => ['university_application']],
            ['key' => 'university_application', 'name' => 'University Application', 'sort_order' => 60, 'next' => ['admission_pending']],
            ['key' => 'admission_pending', 'name' => 'Admission Pending', 'sort_order' => 70, 'next' => ['admission_received']],
            ['key' => 'admission_received', 'name' => 'Admission Received', 'sort_order' => 80, 'next' => ['contract_service_active']],
            ['key' => 'contract_service_active', 'name' => 'Contract/Service Active', 'sort_order' => 90, 'next' => ['visa_preparation']],
            ['key' => 'visa_preparation', 'name' => 'Visa Preparation', 'sort_order' => 100, 'next' => ['visa_submitted']],
            ['key' => 'visa_submitted', 'name' => 'Visa Submitted', 'sort_order' => 110, 'next' => ['visa_processing']],
            ['key' => 'visa_processing', 'name' => 'Visa Processing', 'sort_order' => 120, 'next' => ['visa_approved']],
            ['key' => 'visa_approved', 'name' => 'Visa Approved', 'sort_order' => 130, 'next' => ['completed']],
            ['key' => 'completed', 'name' => 'Completed', 'sort_order' => 140, 'terminal' => true, 'next' => []],
            ['key' => 'cancelled', 'name' => 'Cancelled', 'sort_order' => 900, 'terminal' => true, 'next' => []],
            ['key' => 'rejected', 'name' => 'Rejected', 'sort_order' => 910, 'terminal' => true, 'next' => []],
            ['key' => 'withdrawn', 'name' => 'Withdrawn', 'sort_order' => 920, 'terminal' => true, 'next' => []],
        ] as $stage) {
            DB::table('applicant_stages')->insert([
                'key' => $stage['key'], 'name' => $stage['name'], 'sort_order' => $stage['sort_order'],
                'is_active' => true, 'is_terminal' => $stage['terminal'] ?? false,
                'allowed_next_keys' => json_encode($stage['next']), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('contract_types', fn (Blueprint $table) => $table->dropColumn('description'));
        Schema::table('tasks', fn (Blueprint $table) => $table->dropConstrainedForeignId('applicant_id'));
        Schema::table('contracts', fn (Blueprint $table) => $table->dropConstrainedForeignId('applicant_id'));
        Schema::dropIfExists('applicant_stage_histories');
        Schema::dropIfExists('applicants');
        Schema::dropIfExists('applicant_stages');
        Schema::dropIfExists('universities');
        Schema::dropIfExists('package_types');
        Schema::dropIfExists('languages');
        Schema::dropIfExists('education_levels');
        Schema::dropIfExists('countries');
    }
};
