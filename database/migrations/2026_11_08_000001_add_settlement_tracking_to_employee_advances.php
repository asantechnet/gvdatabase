<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks what has actually been paid back on a salary advance.
 *
 * `employee_advances.salary_id` could only answer "recovered or not", which
 * cannot express a partial repayment (cash handed back by the employee) or an
 * advance larger than one payroll period. Every reduction of the debt is now a
 * row in `employee_advance_settlements`:
 *   - `payroll`: the part a payslip withheld
 *   - `cash`: money the employee handed back
 * `settled_amount` caches the sum and `status` is derived from it, so a debt
 * that reaches zero closes itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_advances', function (Blueprint $table) {
            $table->decimal('settled_amount', 20, 8)->default(0)->after('base_amount');
            $table->string('status', 20)->default('outstanding')->after('settled_amount');
            $table->index('status', 'employee_advances_status_index');
        });

        Schema::create('employee_advance_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_advance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_id')->nullable()->constrained('salaries')->nullOnDelete();
            $table->string('kind', 20)->default('cash');
            $table->decimal('amount', 20, 8);
            $table->date('date');
            $table->string('remarks', 255)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_advance_id', 'kind'], 'employee_advance_settlements_advance_kind_index');
            $table->index('salary_id', 'employee_advance_settlements_salary_id_index');
        });

        // Advances recovered under the old "fully or not at all" rule are
        // already settled; give them their ledger row so releasing works.
        foreach (DB::table('employee_advances')->whereNotNull('salary_id')->get() as $advance) {
            DB::table('employee_advance_settlements')->insert([
                'employee_advance_id' => $advance->id,
                'salary_id' => $advance->salary_id,
                'kind' => 'payroll',
                'amount' => $advance->amount,
                'date' => $advance->recovered_at ? substr((string) $advance->recovered_at, 0, 10) : $advance->advance_date,
                'remarks' => null,
                'recorded_by' => $advance->recorded_by,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('employee_advances')->whereNotNull('salary_id')->update([
            'settled_amount' => DB::raw('amount'),
            'status' => 'settled',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_advance_settlements');

        Schema::table('employee_advances', function (Blueprint $table) {
            $table->dropIndex('employee_advances_status_index');
            $table->dropColumn(['settled_amount', 'status']);
        });
    }
};
