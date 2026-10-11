<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Receipt documents get the same artwork treatment contracts
 * already have: an optional letterhead image plus a relative safe-zone where
 * auto-filled text is allowed to land (see RECEIPTS_AND_DOCUMENT_LETTERHEADS_PLAN.md).
 *
 * `*_letterhead_source` selects where the artwork comes from:
 *   none     — use the built-in design,
 *   contract — reuse the general company contract letterhead,
 *   custom   — use the dedicated uploaded `*_letterhead` file.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('receipt_letterhead')->nullable();
            $table->string('receipt_letterhead_source', 20)->default('custom');
            $table->decimal('receipt_letterhead_safe_x', 5, 2)->default(6.00);
            $table->decimal('receipt_letterhead_safe_y', 5, 2)->default(18.00);
            $table->decimal('receipt_letterhead_safe_width', 5, 2)->default(88.00);
            $table->decimal('receipt_letterhead_safe_height', 5, 2)->default(64.00);

        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'receipt_letterhead',
                'receipt_letterhead_source',
                'receipt_letterhead_safe_x',
                'receipt_letterhead_safe_y',
                'receipt_letterhead_safe_width',
                'receipt_letterhead_safe_height',
            ]);
        });
    }
};
