<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADDITIVE step of the services → contracts merge.
 *
 * Incorporates every operational column of `customer_service` into the
 * contract system WITHOUT dropping anything:
 *
 *  - `contract_service` pivot  ≈ one `customer_service` row per service line
 *  - `contract_service_employee` ≈ `employee_customer_service_commissions`
 *  - `contracts.sold_by_employee_id / marketer_id` ≈ header-level attribution
 *
 * Deliberately NOT done here (see NOTICE.md for the removal list):
 *  - `deadline` / `end_date` / `duration_months` / `services.duration` removal
 *  - dropping `customer_service`, `contracts.service_id`, legacy columns
 *  - deleting CustomerService model / controller / lifecycle services
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_service', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            // Per-customer fee override. NULL = bill the catalogue fee.
            $table->decimal('fee', 15, 2)->nullable();
            $table->foreignId('currency_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('applicant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_package_id')->nullable()->constrained('customer_packages')->nullOnDelete();
            $table->foreignId('sold_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('marketer_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('outsourcing_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->decimal('outsourcing_percentage', 5, 2)->nullable();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('education_level_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('language_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['contract_id', 'service_id'], 'contract_service_unique');
            $table->index('service_id', 'contract_service_service_idx');
        });

        Schema::create('contract_service_employee', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contract_service_id')->constrained('contract_service')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'contract_service_id'], 'contract_service_employee_unique');
        });

        Schema::table('contracts', function (Blueprint $table): void {
            $table->foreignId('sold_by_employee_id')->nullable()->after('employee_id')->constrained('employees')->nullOnDelete();
            $table->foreignId('marketer_id')->nullable()->after('sold_by_employee_id')->constrained('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('marketer_id');
            $table->dropConstrainedForeignId('sold_by_employee_id');
        });
        Schema::dropIfExists('contract_service_employee');
        Schema::dropIfExists('contract_service');
    }
};
