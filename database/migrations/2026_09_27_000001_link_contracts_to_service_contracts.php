<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a generated contract to the catalogue service it was created for and
 * models repeat sales as extensions of the original agreement.
 *
 *  - `source_service_id` is the service the contract was generated for. It is
 *    set whenever a service is assigned to a customer, so "the contract for
 *    this service/customer" is directly retrievable even though the actual
 *    service line lives on `contract_service`.
 *  - `contract_role` is `primary` for the first contract of a (customer,
 *    service) pair and `extension` for subsequent sales.
 *  - `parent_contract_id` points an extension back at its primary contract.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->foreignId('source_service_id')->nullable()->after('applicant_id')
                ->constrained('services')->nullOnDelete();
            $table->string('contract_role')->default('primary')->after('status');
            $table->foreignId('parent_contract_id')->nullable()->after('contract_role')
                ->constrained('contracts')->nullOnDelete();

            $table->index(['applicant_id', 'source_service_id', 'contract_role'], 'contracts_service_primary_index');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropIndex('contracts_service_primary_index');
            $table->dropConstrainedForeignId('parent_contract_id');
            $table->dropColumn('contract_role');
            $table->dropConstrainedForeignId('source_service_id');
        });
    }
};
