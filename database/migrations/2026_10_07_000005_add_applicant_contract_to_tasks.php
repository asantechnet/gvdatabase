<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assigning a contract to an applicant must create work for the person who has
 * to deliver it, and that work has to point at the exact applicant↔contract
 * record — not just at the applicant, where several contracted services would
 * be indistinguishable.
 *
 * The column is named `applicant_contract_id` for the target model. It
 * references the pivot table that currently carries the name `contract_service`;
 * the rename to `applicant_contract` is part of the service/contract
 * restructure and moves this constraint with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tasks', 'applicant_contract_id')) {
            return;
        }

        Schema::table('tasks', function (Blueprint $table): void {
            $table->foreignId('applicant_contract_id')
                ->nullable()
                ->after('applicant_id')
                ->constrained('contract_service')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('tasks', 'applicant_contract_id')) {
            return;
        }

        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropForeign(['applicant_contract_id']);
            $table->dropColumn('applicant_contract_id');
        });
    }
};
