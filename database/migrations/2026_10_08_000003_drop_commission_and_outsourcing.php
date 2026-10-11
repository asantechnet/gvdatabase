<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the commission and outsourcing concepts entirely.
 *
 * Gone: per-service employee commission rates, delivery-recipient pivots,
 * commission accruals/settlements, outsourcing accruals/settlements, the
 * supplier share on a contract line, the invoice-line commission/outsourcing
 * snapshots and the salaried/commission split on employees.
 *
 * Every step is guarded so a fresh database that no longer creates these
 * objects runs cleanly, while databases migrated before the removal are
 * brought to the same final schema.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::withoutForeignKeyConstraints(function (): void {
            Schema::dropIfExists('invoice_service_commission_recipients');
            Schema::dropIfExists('applicant_contract_employee');
            Schema::dropIfExists('commission_accruals');
            Schema::dropIfExists('commission_settlements');
            Schema::dropIfExists('outsourcing_accruals');
            Schema::dropIfExists('outsourcing_settlements');
            Schema::dropIfExists('employee_service_commissions');

            if (Schema::hasTable('applicant_contract')) {
                Schema::table('applicant_contract', function (Blueprint $table): void {
                    if (Schema::hasColumn('applicant_contract', 'outsourcing_company_id')) {
                        $table->dropForeign(['outsourcing_company_id']);
                    }
                });
                Schema::table('applicant_contract', function (Blueprint $table): void {
                    $columns = array_values(array_filter(
                        ['outsourcing_company_id', 'outsourcing_percentage', 'marketer_percentage'],
                        fn (string $column) => Schema::hasColumn('applicant_contract', $column),
                    ));
                    if ($columns !== []) {
                        $table->dropColumn($columns);
                    }
                });
            }

            if (Schema::hasTable('invoice_service')) {
                Schema::table('invoice_service', function (Blueprint $table): void {
                    if (Schema::hasColumn('invoice_service', 'outsourcing_company_id')) {
                        $table->dropForeign(['outsourcing_company_id']);
                        $table->dropIndex('invoice_service_outsourcing_company_idx');
                    }
                });
                Schema::table('invoice_service', function (Blueprint $table): void {
                    $columns = array_values(array_filter(
                        [
                            'outsourcing_company_id',
                            'snapshot_outsourcing_percentage',
                            'snapshot_commission_percentage',
                            'snapshot_marketer_commission_percentage',
                        ],
                        fn (string $column) => Schema::hasColumn('invoice_service', $column),
                    ));
                    if ($columns !== []) {
                        $table->dropColumn($columns);
                    }
                });
            }

            if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'employment_type')) {
                Schema::table('employees', function (Blueprint $table): void {
                    $table->dropColumn('employment_type');
                });
            }
        });
    }

    public function down(): void
    {
        // Retired entities and their discarded data are intentionally not restored.
    }
};
