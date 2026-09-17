<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_service', function (Blueprint $table) {
            $table->foreignId('outsourcing_company_id')->nullable()->after('marketer_id')
                ->constrained('companies')->restrictOnDelete();
            $table->decimal('outsourcing_percentage', 5, 2)->nullable()->after('outsourcing_company_id');
            $table->index('outsourcing_company_id', 'customer_service_outsourcing_company_idx');
        });

        Schema::table('invoice_service', function (Blueprint $table) {
            $table->foreignId('outsourcing_company_id')->nullable()->after('marketer_employee_id')
                ->constrained('companies')->restrictOnDelete();
            $table->decimal('snapshot_outsourcing_percentage', 5, 2)->nullable()->after('outsourcing_company_id');
            $table->index('outsourcing_company_id', 'invoice_service_outsourcing_company_idx');
        });

        Schema::create('outsourcing_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('settlement_number')->nullable()->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('base_currency_id')->constrained('currencies')->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('gross_base_amount', 20, 6);
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('payment_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('expense_id')->nullable()->unique()->constrained('expenses')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'status', 'period_start', 'period_end'], 'outsourcing_settlement_period_idx');
        });

        Schema::create('outsourcing_accruals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('income_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('invoice_service_id')->nullable()->constrained('invoice_service')->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('customer_service_id')->nullable()->constrained('customer_service')->restrictOnDelete();
            $table->foreignId('base_currency_id')->constrained('currencies')->restrictOnDelete();
            $table->foreignId('outsourcing_settlement_id')->nullable()->constrained()->nullOnDelete();
            $table->date('earned_at');
            $table->decimal('source_payment_base_amount', 20, 6);
            $table->decimal('eligible_base_amount', 20, 6);
            $table->decimal('percentage', 5, 2);
            $table->decimal('outsourcing_base_amount', 20, 6);
            $table->string('entry_type')->default('earned');
            $table->text('adjustment_reason')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['income_id', 'invoice_service_id'], 'outsourcing_accrual_source_unique');
            $table->index(['company_id', 'status', 'earned_at'], 'outsourcing_accrual_company_status_idx');
        });

        $now = now();
        $permissions = ['view_outsourcing', 'manage_outsourcing_settlements', 'approve_outsourcing', 'pay_outsourcing'];
        foreach ($permissions as $name) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $name, 'category' => 'finance', 'guard_name' => 'api',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $roles = DB::table('roles')->whereIn('name', ['admin', 'manager'])->pluck('id', 'name');
        $permissionIds = DB::table('permissions')->whereIn('name', $permissions)->pluck('id', 'name');
        foreach ($permissions as $name) {
            if (isset($roles['admin'], $permissionIds[$name])) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $roles['admin'], 'permission_id' => $permissionIds[$name], 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
        foreach (['view_outsourcing', 'manage_outsourcing_settlements', 'approve_outsourcing'] as $name) {
            if (isset($roles['manager'], $permissionIds[$name])) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $roles['manager'], 'permission_id' => $permissionIds[$name], 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('name', [
            'view_outsourcing', 'manage_outsourcing_settlements', 'approve_outsourcing', 'pay_outsourcing',
        ])->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        Schema::dropIfExists('outsourcing_accruals');
        Schema::dropIfExists('outsourcing_settlements');
        Schema::table('invoice_service', function (Blueprint $table) {
            $table->dropIndex('invoice_service_outsourcing_company_idx');
            $table->dropConstrainedForeignId('outsourcing_company_id');
            $table->dropColumn('snapshot_outsourcing_percentage');
        });
        Schema::table('customer_service', function (Blueprint $table) {
            $table->dropIndex('customer_service_outsourcing_company_idx');
            $table->dropConstrainedForeignId('outsourcing_company_id');
            $table->dropColumn('outsourcing_percentage');
        });
    }
};
