<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADDITIVE step of the customers → applicants merge.
 *
 * `customers` and `applicants` are two halves of one real-world record: the
 * billing/CRM party (name, company, contact, email, address, tax number,
 * marketer) and the visa case (stage, country, university, ...). This step
 * moves the party half ONTO `applicants` WITHOUT dropping anything, so a
 * deployment can run this before the code that reads the new columns.
 *
 * Adds:
 *  - `applicants.{name,company,contact,email,address,tax_number,marketer,marketer_id,date}`
 *  - `applicants.parent_applicant_id` — preserves the one-customer-to-many-cases
 *    grouping that `applicants.customer_id` used to express. Self-referencing,
 *    so it survives the drop of the customers table.
 *  - `invoices.applicant_id` and `projects.applicant_id` — the only two tables
 *    that carried `customer_id` WITHOUT already carrying `applicant_id`.
 *
 * Deliberately NOT done here (see the cutover migration + warning.md):
 *  - dropping `customers`, `applicants.customer_id`, or any `customer_id` column
 *  - touching `contract_types.slug = 'customer'` (a contract TYPE, not this entity)
 *  - touching `invoices.customer_*` snapshot columns (immutable issued-document data)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table): void {
            // Party identity, absorbed from `customers`. `name` is tightened to
            // NOT NULL in the cutover step once every row has been backfilled.
            $table->string('name')->nullable()->after('id');
            $table->string('company')->nullable()->after('name');
            $table->string('contact')->nullable()->after('company');
            $table->string('email')->nullable()->after('contact');
            $table->text('address')->nullable()->after('email');
            $table->string('tax_number')->nullable()->after('address');
            // `marketer` is the legacy free-text name; `marketer_id` is the
            // employee attribution. Both are kept, as on the customers table.
            $table->string('marketer')->nullable()->after('tax_number');
            $table->foreignId('marketer_id')->nullable()->after('marketer')->constrained('employees')->nullOnDelete();
            $table->date('date')->nullable()->after('marketer_id');

            // Was `applicants.customer_id`: the first case of a shared billing
            // party is its own root, sibling cases point at it.
            $table->foreignId('parent_applicant_id')->nullable()->after('date')->constrained('applicants')->nullOnDelete();

            $table->index('name', 'applicants_name_idx');
            $table->index('marketer_id', 'applicants_marketer_id_idx');
            $table->index('parent_applicant_id', 'applicants_parent_applicant_id_idx');
        });

        // The two tables whose party link was `customer_id`-only.
        foreach (['invoices', 'projects'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->foreignId('applicant_id')->nullable()->after('customer_id')->constrained('applicants')->nullOnDelete();
                $table->index(['applicant_id'], "{$tableName}_applicant_id_idx");
            });
        }
    }

    public function down(): void
    {
        foreach (['invoices', 'projects'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropConstrainedForeignId('applicant_id');
                $table->dropIndex("{$tableName}_applicant_id_idx");
            });
        }

        Schema::table('applicants', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('parent_applicant_id');
            $table->dropIndex('applicants_name_idx');
            $table->dropIndex('applicants_marketer_id_idx');
            $table->dropIndex('applicants_parent_applicant_id_idx');
            $table->dropConstrainedForeignId('marketer_id');
            $table->dropColumn(['name', 'company', 'contact', 'email', 'address', 'tax_number', 'marketer', 'date', 'parent_applicant_id']);
        });
    }
};
