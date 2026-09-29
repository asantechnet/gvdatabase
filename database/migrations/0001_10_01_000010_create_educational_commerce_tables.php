<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Educational commerce section: scholarships, test offers, customer packages
 * with their service manifests, case wins and the educational bonus ledger.
 *
 * Note: `educational_bonus_sources` intentionally has NO `customer_id` — the
 * historical FK pointed at the dropped customers table (a bug that was never
 * rolled back); the model never had it. See plan doc §2.2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scholarships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->nullable()->constrained('applicants')->nullOnDelete();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->foreignId('education_level_id')->nullable()->constrained('education_levels')->nullOnDelete();
            $table->foreignId('language_id')->nullable()->constrained('languages')->nullOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->foreignId('package_type_id')->nullable()->constrained('package_types')->nullOnDelete();
            $table->date('date');
            $table->decimal('fee', 20, 6);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'status', 'date'], 'scholarships_employee_id_status_date_index');
            $table->index(['country_id', 'education_level_id', 'date'], 'scholarships_country_id_education_level_id_date_index');
        });

        Schema::create('test_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->nullable()->constrained('applicants')->nullOnDelete();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->foreignId('education_level_id')->nullable()->constrained('education_levels')->nullOnDelete();
            $table->foreignId('language_id')->nullable()->constrained('languages')->nullOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->foreignId('package_type_id')->nullable()->constrained('package_types')->nullOnDelete();
            $table->date('date');
            $table->decimal('fee', 20, 6);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'status', 'date'], 'test_offers_employee_id_status_date_index');
            $table->index(['country_id', 'education_level_id', 'date'], 'test_offers_country_id_education_level_id_date_index');
        });

        Schema::create('customer_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->nullable()->constrained('applicants')->nullOnDelete();
            $table->foreignId('package_id')->constrained('package_types')->restrictOnDelete();
            $table->foreignId('sold_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->decimal('fee', 20, 6)->nullable();
            $table->date('purchased_at');
            $table->string('status', 30)->default('active');
            $table->string('issuance_key')->unique();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['applicant_id', 'status'], 'customer_packages_customer_id_applicant_id_status_index');
        });

        Schema::create('package_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('package_types')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->restrictOnDelete();
            $table->decimal('allocated_fee', 20, 6)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['package_id', 'service_id'], 'package_service_package_id_service_id_unique');
        });

        Schema::create('case_wins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('applicants')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('applicant_stage_history_id')->nullable()->constrained('applicant_stage_histories')->nullOnDelete();
            $table->timestamp('won_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('stage_key', 100);
            $table->timestamps();
            $table->softDeletes();

            $table->unique('applicant_id', 'case_wins_applicant_id_unique');
            $table->unique('applicant_stage_history_id', 'case_wins_applicant_stage_history_id_unique');
            $table->index(['employee_id', 'won_at'], 'case_wins_employee_id_won_at_index');
        });

        Schema::create('educational_bonus_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('applicant_id')->nullable()->constrained('applicants')->nullOnDelete();
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->decimal('source_amount', 20, 6);
            $table->decimal('bonus_amount', 20, 6)->default(0);
            $table->string('status')->default('pending');
            $table->string('rule_source')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['source_type', 'source_id', 'employee_id'], 'educational_bonus_source_unique');
            $table->index(['source_type', 'source_id'], 'educational_bonus_sources_source_type_source_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('educational_bonus_sources');
        Schema::dropIfExists('case_wins');
        Schema::dropIfExists('package_service');
        Schema::dropIfExists('customer_packages');
        Schema::dropIfExists('test_offers');
        Schema::dropIfExists('scholarships');
    }
};
