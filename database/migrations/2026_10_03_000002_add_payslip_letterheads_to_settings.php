<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payslips get the same letterhead treatment as contracts, receipts and
 * invoices: an optional artwork image plus a relative safe-zone where the
 * generated payslip content is allowed to land (see
 * TASKS_ATTACHMENTS_PAYSLIP_PLAN.md).
 *
 * `payslip_letterhead_source` selects where the artwork comes from:
 *   none     — use the built-in design,
 *   contract — reuse the general company contract letterhead,
 *   custom   — use the dedicated uploaded `payslip_letterhead` file.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('payslip_letterhead')->nullable();
            $table->string('payslip_letterhead_source', 20)->default('custom');
            $table->decimal('payslip_letterhead_safe_x', 5, 2)->default(8.00);
            $table->decimal('payslip_letterhead_safe_y', 5, 2)->default(14.00);
            $table->decimal('payslip_letterhead_safe_width', 5, 2)->default(84.00);
            $table->decimal('payslip_letterhead_safe_height', 5, 2)->default(72.00);
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'payslip_letterhead',
                'payslip_letterhead_source',
                'payslip_letterhead_safe_x',
                'payslip_letterhead_safe_y',
                'payslip_letterhead_safe_width',
                'payslip_letterhead_safe_height',
            ]);
        });
    }
};
