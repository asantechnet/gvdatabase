<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A direct Academy student is a payer without an applicant or contract. The
 * income row keeps that identity so the receipt and the student card can show
 * the same payments without inventing an applicant record.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('incomes', 'student_id')) {
            return;
        }

        Schema::table('incomes', function (Blueprint $table): void {
            $table->foreignId('student_id')->nullable()->after('applicant_id')
                ->constrained('students')->nullOnDelete();
            $table->index(['student_id', 'date'], 'incomes_student_id_date_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('incomes', 'student_id')) {
            return;
        }

        Schema::table('incomes', function (Blueprint $table): void {
            $table->dropForeign(['student_id']);
            $table->dropIndex('incomes_student_id_date_index');
            $table->dropColumn('student_id');
        });
    }
};
