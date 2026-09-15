<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('invoice_service', 'marketer_employee_id')) {
            Schema::table('invoice_service', function (Blueprint $table) {
                $table->foreignId('marketer_employee_id')->nullable()->after('employee_id')
                    ->constrained('employees')->nullOnDelete();
                $table->decimal('snapshot_marketer_commission_percentage', 5, 2)->nullable()
                    ->after('snapshot_commission_percentage');
                $table->index('marketer_employee_id', 'invoice_service_marketer_employee_idx');
            });
        }

        if (! Schema::hasColumn('commission_accruals', 'commission_role')) {
            Schema::table('commission_accruals', function (Blueprint $table) {
                // One payment can legitimately create two earnings for one
                // line: delivery and customer acquisition.
                $table->string('commission_role', 20)->default('delivery')->after('entry_type');
            });
        }

        $indexNames = collect(Schema::getIndexes('commission_accruals'))->pluck('name')->all();

        // The original unique key may be MySQL's only usable supporting index
        // for either source foreign key. Preserve both before replacing it.
        if (! in_array('commission_accrual_income_fk_idx', $indexNames, true)) {
            Schema::table('commission_accruals', function (Blueprint $table) {
                $table->index('income_id', 'commission_accrual_income_fk_idx');
            });
            $indexNames[] = 'commission_accrual_income_fk_idx';
        }

        if (! in_array('commission_accrual_invoice_service_fk_idx', $indexNames, true)) {
            Schema::table('commission_accruals', function (Blueprint $table) {
                $table->index('invoice_service_id', 'commission_accrual_invoice_service_fk_idx');
            });
            $indexNames[] = 'commission_accrual_invoice_service_fk_idx';
        }

        if (in_array('commission_accruals_income_id_invoice_service_id_unique', $indexNames, true)) {
            Schema::table('commission_accruals', function (Blueprint $table) {
                $table->dropUnique(['income_id', 'invoice_service_id']);
            });
            $indexNames = array_values(array_diff($indexNames, ['commission_accruals_income_id_invoice_service_id_unique']));
        }

        if (! in_array('commission_accrual_source_role_unique', $indexNames, true)) {
            Schema::table('commission_accruals', function (Blueprint $table) {
                $table->unique(['income_id', 'invoice_service_id', 'commission_role'], 'commission_accrual_source_role_unique');
            });
        }

        if (! in_array('commission_accrual_role_status_idx', $indexNames, true)) {
            Schema::table('commission_accruals', function (Blueprint $table) {
                $table->index(['employee_id', 'commission_role', 'status', 'earned_at'], 'commission_accrual_role_status_idx');
            });
        }

    }

    public function down(): void
    {
        Schema::table('commission_accruals', function (Blueprint $table) {
            $table->dropIndex('commission_accrual_role_status_idx');
            $table->dropUnique('commission_accrual_source_role_unique');
            $table->dropIndex('commission_accrual_income_fk_idx');
            $table->dropIndex('commission_accrual_invoice_service_fk_idx');
            $table->dropColumn('commission_role');
            $table->unique(['income_id', 'invoice_service_id']);
        });

        Schema::table('invoice_service', function (Blueprint $table) {
            $table->dropIndex('invoice_service_marketer_employee_idx');
            $table->dropConstrainedForeignId('marketer_employee_id');
            $table->dropColumn('snapshot_marketer_commission_percentage');
        });
    }
};
