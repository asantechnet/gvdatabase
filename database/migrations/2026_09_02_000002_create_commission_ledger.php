<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_service', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->after('service_id')->constrained()->restrictOnDelete();
            $table->decimal('snapshot_commission_percentage', 5, 2)->nullable()->after('employee_id');
            $table->index(['employee_id', 'service_id']);
        });

        Schema::create('commission_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('settlement_number')->nullable()->unique();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
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
            $table->index(['employee_id', 'status', 'period_start', 'period_end'], 'commission_settlement_period_idx');
        });

        Schema::create('commission_accruals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('income_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_service_id')->constrained('invoice_service')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->foreignId('base_currency_id')->constrained('currencies')->restrictOnDelete();
            $table->foreignId('commission_settlement_id')->nullable()->constrained()->nullOnDelete();
            $table->date('earned_at');
            $table->decimal('source_payment_base_amount', 20, 6);
            $table->decimal('eligible_base_amount', 20, 6);
            $table->decimal('percentage', 5, 2);
            $table->decimal('commission_base_amount', 20, 6);
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['income_id', 'invoice_service_id']);
            $table->index(['employee_id', 'status', 'earned_at']);
        });

        $now = now();
        $permissionNames = ['view_commissions', 'manage_commission_settlements', 'approve_commissions', 'pay_commissions'];
        foreach ($permissionNames as $name) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $name, 'category' => 'human_resources', 'guard_name' => 'api',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $roleIds = DB::table('roles')->whereIn('name', ['admin', 'manager'])->pluck('id', 'name');
        $permissionIds = DB::table('permissions')->whereIn('name', $permissionNames)->pluck('id', 'name');
        foreach ($permissionNames as $name) {
            if (isset($roleIds['admin'], $permissionIds[$name])) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $roleIds['admin'], 'permission_id' => $permissionIds[$name],
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
        foreach (['view_commissions', 'manage_commission_settlements', 'approve_commissions'] as $name) {
            if (isset($roleIds['manager'], $permissionIds[$name])) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $roleIds['manager'], 'permission_id' => $permissionIds[$name],
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('name', [
            'view_commissions', 'manage_commission_settlements', 'approve_commissions', 'pay_commissions',
        ])->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        Schema::dropIfExists('commission_accruals');
        Schema::dropIfExists('commission_settlements');
        Schema::table('invoice_service', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropIndex(['employee_id', 'service_id']);
            $table->dropColumn(['employee_id', 'snapshot_commission_percentage']);
        });
    }
};
