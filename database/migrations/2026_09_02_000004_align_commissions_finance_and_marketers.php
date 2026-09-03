<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep the FK name below MySQL's 64-character identifier limit. The
        // table check also lets a failed first attempt (where MySQL created the
        // table before rejecting the old generated FK name) resume cleanly.
        if (! Schema::hasTable('employee_customer_service_commissions')) {
            Schema::create('employee_customer_service_commissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('customer_service_id');
                $table->decimal('percentage', 5, 2);
                $table->timestamps();

                $table->unique(['employee_id', 'customer_service_id'], 'employee_customer_service_commission_unique');
            });
        }

        Schema::table('employee_customer_service_commissions', function (Blueprint $table) {
            $table->foreign('customer_service_id', 'emp_cust_service_comm_cs_fk')
                ->references('id')
                ->on('customer_service')
                ->cascadeOnDelete();
        });

        Schema::table('invoice_service', function (Blueprint $table) {
            $table->foreignId('customer_service_id')->nullable()->constrained('customer_service')->nullOnDelete();
            $table->index('customer_service_id', 'invoice_service_customer_service_idx');
        });

        Schema::table('commission_accruals', function (Blueprint $table) {
            $table->foreignId('customer_service_id')->nullable()->constrained('customer_service')->nullOnDelete();
            $table->index('customer_service_id', 'commission_accrual_customer_service_idx');
        });

        Schema::table('incomes', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->index('company_id', 'income_company_idx');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->index('company_id', 'expense_company_idx');
            $table->index('project_id', 'expense_project_idx');
        });

        Schema::table('positions', function (Blueprint $table) {
            $table->string('system_key', 80)->nullable()->unique();
        });

        Schema::table('marketers', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->unique()->constrained()->nullOnDelete();
        });

        $now = now();

        // Previous commission settings were attached to the global service
        // catalogue. Copy them to every matching customer-service agreement so
        // existing assignments continue to work, while all future edits use the
        // contractual (customer-specific) fee and currency.
        DB::table('employee_service_commissions')->orderBy('id')->each(function (object $legacy) use ($now): void {
            $customerServiceIds = DB::table('customer_service')
                ->where('service_id', $legacy->service_id)
                ->whereNull('deleted_at')
                ->pluck('id');

            foreach ($customerServiceIds as $customerServiceId) {
                DB::table('employee_customer_service_commissions')->insertOrIgnore([
                    'employee_id' => $legacy->employee_id,
                    'customer_service_id' => $customerServiceId,
                    'percentage' => $legacy->percentage,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });

        // Historic invoice rows keep their stored prices. This only adds the
        // source agreement link where it is unambiguous for reporting/audits.
        DB::table('invoice_service')->orderBy('id')->each(function (object $line): void {
            $invoice = DB::table('invoices')->select('customer_id')->where('id', $line->invoice_id)->first();
            if (! $invoice) {
                return;
            }

            $customerServiceId = DB::table('customer_service')
                ->where('customer_id', $invoice->customer_id)
                ->where('service_id', $line->service_id)
                ->whereNull('deleted_at')
                ->value('id');

            if ($customerServiceId) {
                DB::table('invoice_service')->where('id', $line->id)->update(['customer_service_id' => $customerServiceId]);
            }
        });

        DB::table('commission_accruals')->whereNotNull('invoice_service_id')->orderBy('id')->each(function (object $accrual): void {
            $customerServiceId = DB::table('invoice_service')->where('id', $accrual->invoice_service_id)->value('customer_service_id');
            if ($customerServiceId) {
                DB::table('commission_accruals')->where('id', $accrual->id)->update(['customer_service_id' => $customerServiceId]);
            }
        });

        // Marketers are employees, not a separate directory. Keep legacy rows
        // for audit/history, but link each one to an employee in the protected
        // Marketer position. CRM records retain their existing marketer names.
        $marketerPositionId = DB::table('positions')->where('system_key', 'marketer')->value('id');
        if (! $marketerPositionId) {
            $marketerPositionId = DB::table('positions')->insertGetId([
                'title' => 'Marketer',
                'description' => 'System position for employees who own marketing and referral relationships.',
                'salary' => 0,
                'status' => 'active',
                'system_key' => 'marketer',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('marketers')->whereNull('deleted_at')->orderBy('id')->each(function (object $marketer) use ($marketerPositionId, $now): void {
            $employee = $marketer->employee_id
                ? DB::table('employees')->where('id', $marketer->employee_id)->first()
                : ($marketer->user_id ? DB::table('employees')->where('user_id', $marketer->user_id)->first() : null);

            if ($employee) {
                DB::table('employees')->where('id', $employee->id)->update(['position_id' => $marketerPositionId, 'updated_at' => $now]);
                $employeeId = $employee->id;
            } else {
                $employeeId = DB::table('employees')->insertGetId([
                    'user_id' => $marketer->user_id,
                    'name' => $marketer->name,
                    'father_name' => 'Not recorded',
                    'contact' => $marketer->phone ?: 'Not recorded',
                    'id_number' => 'LEGACY-MARKETER-'.$marketer->id,
                    'education_lvl' => 'Not recorded',
                    'position_id' => $marketerPositionId,
                    'employment_type' => 'salaried',
                    'salary' => 0,
                    'addr' => 'Not recorded',
                    'department' => 'Marketing',
                    'join_date' => $now->toDateString(),
                    'contract_duration' => '',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('marketers')->where('id', $marketer->id)->update(['employee_id' => $employeeId, 'updated_at' => $now]);
        });
    }

    public function down(): void
    {
        Schema::table('marketers', function (Blueprint $table) {
            $table->dropUnique(['employee_id']);
        });

        Schema::table('marketers', function (Blueprint $table) {
            // dropConstrainedForeignId is required for SQLite table rebuilds;
            // the unique index must already be gone before it drops the field.
            $table->dropConstrainedForeignId('employee_id');
        });

        Schema::table('positions', function (Blueprint $table) {
            $table->dropUnique(['system_key']);
            $table->dropColumn('system_key');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex('expense_company_idx');
            $table->dropIndex('expense_project_idx');
            $table->dropConstrainedForeignId('company_id');
            $table->dropConstrainedForeignId('project_id');
        });

        Schema::table('incomes', function (Blueprint $table) {
            $table->dropIndex('income_company_idx');
            $table->dropConstrainedForeignId('company_id');
        });

        Schema::table('commission_accruals', function (Blueprint $table) {
            $table->dropIndex('commission_accrual_customer_service_idx');
            $table->dropConstrainedForeignId('customer_service_id');
        });

        Schema::table('invoice_service', function (Blueprint $table) {
            $table->dropIndex('invoice_service_customer_service_idx');
            $table->dropConstrainedForeignId('customer_service_id');
        });

        Schema::dropIfExists('employee_customer_service_commissions');
    }
};
