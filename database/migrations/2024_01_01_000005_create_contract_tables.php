<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * System contract type slugs act as stable machine identifiers.
     * Display names may be translated, but logic must rely on these slugs.
     */
    private const SYSTEM_TYPES = [
        ['name' => 'Employment', 'slug' => 'employment'],
        ['name' => 'Customer', 'slug' => 'customer'],
        ['name' => 'Company', 'slug' => 'company'],
    ];

    public function up(): void
    {
        Schema::create('contract_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        foreach (self::SYSTEM_TYPES as $type) {
            DB::table('contract_types')->insert([
                'name' => $type['name'],
                'slug' => $type['slug'],
                'is_system' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::create('companies', function (Blueprint $table) {
            $table->id();

            // Identity
            $table->string('legal_name');
            $table->string('trade_name')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('national_id')->nullable();
            $table->string('legal_form')->nullable();

            // Contact
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();

            // Structured address
            $table->string('country')->nullable();
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();
            $table->text('address_line')->nullable();

            // Business information
            $table->string('industry')->nullable();
            $table->text('description')->nullable();

            // Authorized representative / signatory
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
            $table->string('header')->nullable();
            $table->longText('content');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('contract_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contract_type_id')->nullable()->constrained('contract_types')->nullOnDelete();
            $table->string('name');
            $table->string('type')->nullable();
            $table->text('cover_text');
            // longText, NOT json: JSON columns carry an implicit json_valid
            // CHECK constraint that rejects contract HTML.
            $table->longText('content');
            $table->timestamps();
            $table->softDeletes();
            $table->index('position_id');
        });

        Schema::create('contract_policy_contract_template', function (Blueprint $table) {
            $table->foreignId('contract_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_policy_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->primary(['contract_template_id', 'contract_policy_id'], 'contract_template_policy_primary');
        });

        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_number')->nullable()->unique();
            $table->foreignId('template_id')->nullable()->constrained('contract_templates');
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('contract_type_id')->nullable()->constrained('contract_types')->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('type')->nullable();
            $table->string('status');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('counterparty');
            $table->string('counterparty_company')->nullable();
            $table->string('counterparty_email')->nullable();
            $table->string('counterparty_contact')->nullable();
            $table->text('counterparty_address')->nullable();
            $table->boolean('is_cancelled')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cover_text')->nullable();
            $table->longText('content')->nullable();
            $table->boolean('is_custom')->default(false);
            $table->json('snapshot_data')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('contract_templates');
        Schema::dropIfExists('contract_policy_contract_template');
        Schema::dropIfExists('contract_policies');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('contract_types');
    }
};
