<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes invoicing entirely.
 *
 * Invoices, their per-service lines and payment details are no longer part of
 * the product: an income receipt is the only payment record and it always
 * describes one contracted service. Every step is guarded so a fresh database
 * that no longer creates these objects runs cleanly.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::withoutForeignKeyConstraints(function (): void {
            Schema::dropIfExists('invoice_service_commission_recipients');
            Schema::dropIfExists('invoice_service');
            Schema::dropIfExists('invoices');
            Schema::dropIfExists('payment_details');

            if (Schema::hasTable('incomes') && Schema::hasColumn('incomes', 'invoice_id')) {
                Schema::table('incomes', function (Blueprint $table): void {
                    $table->dropForeign(['invoice_id']);
                });
                Schema::table('incomes', function (Blueprint $table): void {
                    try {
                        $table->dropIndex('incomes_invoice_id_index');
                    } catch (Throwable) {
                        // The index is absent on some migrated databases.
                    }
                });
                Schema::table('incomes', function (Blueprint $table): void {
                    $columns = array_values(array_filter(
                        ['invoice_id', 'invoice_amount'],
                        fn (string $column) => Schema::hasColumn('incomes', $column),
                    ));
                    if ($columns !== []) {
                        $table->dropColumn($columns);
                    }
                });
            }

            if (Schema::hasTable('settings') && Schema::hasColumn('settings', 'invoice_letterhead')) {
                Schema::table('settings', function (Blueprint $table): void {
                    $table->dropColumn([
                        'invoice_letterhead',
                        'invoice_letterhead_source',
                        'invoice_letterhead_safe_x',
                        'invoice_letterhead_safe_y',
                        'invoice_letterhead_safe_width',
                        'invoice_letterhead_safe_height',
                    ]);
                });
            }
        });
    }

    public function down(): void
    {
        // Retired entities and their discarded data are intentionally not restored.
    }
};
