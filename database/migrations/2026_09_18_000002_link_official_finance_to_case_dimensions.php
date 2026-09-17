<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan §19–20: official income/expense records must be traceable back to
 * the business activity that caused them. Adds the optional applicant,
 * visitor, and employee dimensions (all nullable — office-level records
 * legitimately reference none of them).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['incomes', 'expenses'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('applicant_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
                $table->foreignId('visitor_id')->nullable()->after('applicant_id')->constrained()->nullOnDelete();
                $table->foreignId('employee_id')->nullable()->after('visitor_id')->constrained()->nullOnDelete();
                $table->index(['applicant_id', 'date']);
            });
        }
    }

    public function down(): void
    {
        foreach (['incomes', 'expenses'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropIndex(['applicant_id', 'date']);
                $table->dropConstrainedForeignId('applicant_id');
                $table->dropConstrainedForeignId('visitor_id');
                $table->dropConstrainedForeignId('employee_id');
            });
        }
    }
};
