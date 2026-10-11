<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the counterparty "companies" entity entirely.
 *
 * A company was an optional counterparty record linked from contracts and
 * incomes, and a free-text label on visitors. None of it remains: contracts
 * keep their free-text counterparty fields and the firm's own branding lives
 * in settings (CompanyProfile). Every step is guarded so a fresh database that
 * no longer creates these objects runs cleanly.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::withoutForeignKeyConstraints(function (): void {
            if (Schema::hasTable('contracts') && Schema::hasColumn('contracts', 'company_id')) {
                Schema::table('contracts', function (Blueprint $table): void {
                    try {
                        $table->dropForeign(['company_id']);
                    } catch (Throwable) {
                        // SQLite rebuilds the table; an unnamed FK may be absent.
                    }
                });
                Schema::table('contracts', function (Blueprint $table): void {
                    $table->dropColumn('company_id');
                });
            }

            if (Schema::hasTable('incomes') && Schema::hasColumn('incomes', 'company_id')) {
                Schema::table('incomes', function (Blueprint $table): void {
                    try {
                        $table->dropForeign(['company_id']);
                    } catch (Throwable) {
                        // The foreign key is absent on some migrated databases.
                    }
                });
                Schema::table('incomes', function (Blueprint $table): void {
                    try {
                        $table->dropIndex('income_company_idx');
                    } catch (Throwable) {
                        // The index is absent on some migrated databases.
                    }
                });
                Schema::table('incomes', function (Blueprint $table): void {
                    $table->dropColumn('company_id');
                });
            }

            if (Schema::hasTable('visitors') && Schema::hasColumn('visitors', 'company')) {
                Schema::table('visitors', function (Blueprint $table): void {
                    $table->dropColumn('company');
                });
            }

            Schema::dropIfExists('companies');
        });
    }

    public function down(): void
    {
        // Retired entities and their discarded data are intentionally not restored.
    }
};
