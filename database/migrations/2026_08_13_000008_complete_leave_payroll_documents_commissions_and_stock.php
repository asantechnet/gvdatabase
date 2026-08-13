<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('email')->nullable()->after('contact');
            $table->text('address')->nullable()->after('email');
            $table->string('tax_number')->nullable()->after('address');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->date('end_date')->nullable()->after('start_date');
            $table->string('counterparty_company')->nullable()->after('counterparty');
            $table->string('counterparty_email')->nullable()->after('counterparty_company');
            $table->string('counterparty_contact')->nullable()->after('counterparty_email');
            $table->text('counterparty_address')->nullable()->after('counterparty_contact');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->date('issue_date')->nullable()->after('status');
            $table->date('due_date')->nullable()->after('issue_date');
            $table->string('customer_company')->nullable()->after('deptor');
            $table->string('customer_email')->nullable()->after('customer_company');
            $table->string('customer_contact')->nullable()->after('customer_email');
            $table->text('customer_address')->nullable()->after('customer_contact');
            $table->string('customer_tax_number')->nullable()->after('customer_address');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->string('employment_type')->default('salaried')->after('position_id');
            $table->string('tax_number')->nullable()->after('id_number');
            $table->string('bank_account_number')->nullable()->after('tax_number');
            $table->decimal('salary', 10, 2)->nullable()->change();
        });

        Schema::create('employee_service_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->decimal('percentage', 5, 2);
            $table->timestamps();
            $table->unique(['employee_id', 'service_id']);
        });

        Schema::table('paid_time_offs', function (Blueprint $table) {
            $table->boolean('is_granted')->default(false)->after('reason');
            $table->boolean('is_paid')->nullable()->after('is_granted');
            $table->foreignId('requested_by')->nullable()->after('is_paid')->constrained('users')->nullOnDelete();
            $table->foreignId('granted_by')->nullable()->after('requested_by')->constrained('users')->nullOnDelete();
            $table->timestamp('granted_at')->nullable()->after('granted_by');
        });

        Schema::table('salaries', function (Blueprint $table) {
            $table->unsignedSmallInteger('paid_off_days')->default(0)->after('worked_days');
            $table->unsignedSmallInteger('absent_days')->default(0)->after('paid_off_days');
            $table->unsignedSmallInteger('unpaid_off_days')->default(0)->after('absent_days');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('salary_id')->nullable()->unique()->after('customer_id')->constrained()->cascadeOnDelete();
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->decimal('price', 14, 2)->default(0)->after('quantity');
        });

        $now = now();
        $permissions = ['request_own_paid_time_off', 'grant_paid_time_off', 'print_paid_time_off'];
        foreach ($permissions as $name) {
            DB::table('permissions')->updateOrInsert(['name' => $name], [
                'category' => 'human_resources', 'guard_name' => 'api', 'deleted_at' => null, 'updated_at' => $now, 'created_at' => $now,
            ]);
        }
        $permissionIds = DB::table('permissions')->whereIn('name', $permissions)->pluck('id', 'name');
        foreach (['admin', 'manager', 'staff', 'viewer'] as $role) {
            DB::table('role_permissions')->updateOrInsert([
                'role' => $role, 'permission_id' => $permissionIds['request_own_paid_time_off'],
            ], ['updated_at' => $now, 'created_at' => $now]);
        }
        foreach (['admin', 'manager'] as $role) {
            foreach (['grant_paid_time_off', 'print_paid_time_off'] as $permission) {
                DB::table('role_permissions')->updateOrInsert([
                    'role' => $role, 'permission_id' => $permissionIds[$permission],
                ], ['updated_at' => $now, 'created_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('name', ['request_own_paid_time_off', 'grant_paid_time_off', 'print_paid_time_off'])->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        Schema::table('stocks', fn (Blueprint $table) => $table->dropColumn('price'));
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['salary_id']);
            $table->dropUnique(['salary_id']);
            $table->dropColumn('salary_id');
        });
        Schema::table('salaries', fn (Blueprint $table) => $table->dropColumn(['paid_off_days', 'absent_days', 'unpaid_off_days']));
        Schema::table('paid_time_offs', function (Blueprint $table) {
            $table->dropForeign(['requested_by']);
            $table->dropForeign(['granted_by']);
            $table->dropColumn(['is_granted', 'is_paid', 'requested_by', 'granted_by', 'granted_at']);
        });
        Schema::dropIfExists('employee_service_commissions');
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('salary', 10, 2)->nullable(false)->change();
            $table->dropColumn(['employment_type', 'tax_number', 'bank_account_number']);
        });
        Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn(['issue_date', 'due_date', 'customer_company', 'customer_email', 'customer_contact', 'customer_address', 'customer_tax_number']));
        Schema::table('contracts', fn (Blueprint $table) => $table->dropColumn(['end_date', 'counterparty_company', 'counterparty_email', 'counterparty_contact', 'counterparty_address']));
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn(['email', 'address', 'tax_number']));
    }
};
