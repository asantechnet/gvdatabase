<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contract section. Order within the section: types -> companies -> policies
 * -> templates -> versions -> contracts -> contract_service -> commission
 * recipients -> document designs.
 *
 * FKs deferred to 0001_15_01_000015_add_cross_section_foreign_keys (targets
 * live in later sections):
 *   - contract_templates.service_id        -> services (nullOnDelete)
 *   - contract_document_designs.service_id -> services (cascadeOnDelete)
 *   - contract_service.customer_package_id -> customer_packages (nullOnDelete)
 *
 * The three system contract types (employment / customer / other) are seeded
 * here, exactly as the final historical state left them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->string('slug')->unique();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('legal_name');
            $table->string('trade_name')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('national_id')->nullable();
            $table->string('legal_form')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->string('country')->nullable();
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();
            $table->text('address_line')->nullable();
            $table->string('industry')->nullable();
            $table->text('description')->nullable();
            $table->string('representative_name')->nullable();
            $table->string('representative_title')->nullable();
            $table->string('representative_phone')->nullable();
            $table->string('representative_email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('contract_policies', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('type');
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('language_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->string('header')->nullable();
            $table->longText('content');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'is_active'], 'contract_policies_type_active_index');
            $table->index('country_id', 'contract_policies_country_id_foreign');
            $table->index('language_id', 'contract_policies_language_id_foreign');
            $table->index(['type', 'country_id', 'language_id', 'is_active'], 'contract_policy_scope_index');
        });

        DB::table('contract_types')->insert([
            ['name' => 'Employment', 'slug' => 'employment', 'is_system' => true, 'is_active' => true],
            // The slug stays "customer" (see warning.md); the label is neutral.
            ['name' => 'Customer', 'slug' => 'customer', 'is_system' => true, 'is_active' => true],
            ['name' => 'Other', 'slug' => 'other', 'is_system' => true, 'is_active' => true],
        ]);

        Schema::create('contract_templates', function (Blueprint $table) {
            $table->id();
            // Index name preserved from the original create; the FK is added
            // below so the index keeps its historical name.
            $table->unsignedBigInteger('position_id')->nullable();
            $table->foreignId('contract_type_id')->nullable()->constrained('contract_types')->nullOnDelete();
            // FK added in the completion pass (services live in the finance section).
            $table->unsignedBigInteger('service_id')->nullable()->index('contract_templates_service_id_index');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->nullable();
            $table->text('cover_text');
            $table->longText('content');
            $table->timestamps();
            $table->softDeletes();

            $table->index('position_id', 'contract_templates_position_id_index');
        });

        Schema::table('contract_templates', function (Blueprint $table) {
            $table->foreign('position_id')->references('id')->on('positions')->nullOnDelete();
        });

        Schema::create('contract_policy_contract_template', function (Blueprint $table) {
            $table->unsignedBigInteger('contract_template_id');
            $table->unsignedBigInteger('contract_policy_id');
            $table->unsignedInteger('sort_order')->default(0);

            $table->primary(['contract_template_id', 'contract_policy_id']);
            $table->foreign('contract_policy_id', 'contract_policy_contract_template_contract_policy_id_foreign')
                ->references('id')->on('contract_policies')->cascadeOnDelete();
            $table->foreign('contract_template_id', 'contract_policy_contract_template_contract_template_id_foreign')
                ->references('id')->on('contract_templates')->cascadeOnDelete();
        });

        Schema::create('contract_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_template_id')->constrained('contract_templates')->restrictOnDelete();
            $table->foreignId('language_id')->constrained('languages')->restrictOnDelete();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('name')->default('Untitled version');
            $table->longText('content');
            $table->string('status')->default('draft');
            $table->boolean('is_current')->default(false);
            $table->string('current_scope_key', 100)->nullable()->unique('contract_template_versions_current_scope_key_unique');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['contract_template_id', 'country_id', 'language_id', 'version_number'], 'ctv_template_country_language_version_unique');
            $table->index(['contract_template_id', 'language_id', 'is_current'], 'ctv_template_language_current_index');
            $table->index(['contract_template_id', 'country_id', 'language_id', 'is_current'], 'ctv_country_language_current_index');
        });

        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_number')->nullable()->unique();
            $table->foreignId('template_id')->nullable()->constrained('contract_templates')->restrictOnDelete();
            $table->foreignId('contract_template_version_id')->nullable()->constrained('contract_template_versions')->nullOnDelete();
            $table->foreignId('contract_country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->foreignId('contract_language_id')->nullable()->constrained('languages')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('sold_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('marketer_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('applicant_id')->nullable()->constrained('applicants')->nullOnDelete();
            // FK added in the completion pass (services live in the finance section).
            $table->unsignedBigInteger('source_service_id')->nullable()->index('contracts_source_service_id_foreign');
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('contract_type_id')->nullable()->constrained('contract_types')->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('type')->nullable();
            $table->string('status');
            $table->string('contract_role')->default('primary');
            $table->foreignId('parent_contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->date('start_date');
            $table->string('counterparty');
            $table->string('counterparty_company')->nullable();
            $table->string('counterparty_email')->nullable();
            $table->string('counterparty_contact')->nullable();
            $table->text('counterparty_address')->nullable();
            $table->string('company_signatory_name')->nullable();
            $table->boolean('is_cancelled')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cover_text')->nullable();
            $table->longText('content')->nullable();
            $table->boolean('is_custom')->default(false);
            $table->json('snapshot_data')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('applicant_id', 'contracts_applicant_id_index');
            $table->index(['applicant_id', 'source_service_id', 'contract_role'], 'contracts_service_primary_index');
        });

        Schema::create('contract_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            // FK added in the completion pass (services live in the finance section).
            $table->unsignedBigInteger('service_id')->index('contract_service_service_idx');
            $table->decimal('fee', 15, 2)->nullable();
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->foreignId('applicant_id')->nullable()->constrained('applicants')->nullOnDelete();
            // FK added in the completion pass (customer_packages live in the
            // educational commerce section).
            $table->unsignedBigInteger('customer_package_id')->nullable()->index('contract_service_customer_package_id_foreign');
            $table->foreignId('sold_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('marketer_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('outsourcing_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->decimal('outsourcing_percentage', 5, 2)->nullable();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->foreignId('education_level_id')->nullable()->constrained('education_levels')->nullOnDelete();
            $table->foreignId('language_id')->nullable()->constrained('languages')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['contract_id', 'service_id'], 'contract_service_unique');
        });

        Schema::create('contract_service_employee', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_service_id')->constrained('contract_service')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'contract_service_id'], 'contract_service_employee_unique');
        });

        Schema::create('contract_document_designs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_type_id')->constrained('contract_types')->cascadeOnDelete();
            // FK added in the completion pass (services live in the finance section).
            $table->unsignedBigInteger('service_id')->nullable()->index('contract_document_designs_service_id_foreign');
            $table->string('letterhead')->nullable();
            $table->string('cover_design')->nullable();
            $table->decimal('safe_x', 5, 2)->default(8.50);
            $table->decimal('safe_y', 5, 2)->default(15.00);
            $table->decimal('safe_width', 5, 2)->default(83.00);
            $table->decimal('safe_height', 5, 2)->default(70.00);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['contract_type_id', 'service_id'], 'contract_design_type_service_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_document_designs');
        Schema::dropIfExists('contract_service_employee');
        Schema::dropIfExists('contract_service');
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('contract_template_versions');
        Schema::dropIfExists('contract_policy_contract_template');
        Schema::dropIfExists('contract_templates');
        Schema::dropIfExists('contract_policies');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('contract_types');
    }
};
