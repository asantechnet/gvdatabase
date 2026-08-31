<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 30)->default('active');
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('boards', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index(['project_id', 'order']);
        });

        Schema::create('contract_service', function (Blueprint $table) {
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->primary(['contract_id', 'service_id']);
        });

        Schema::create('project_service', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->primary(['project_id', 'service_id']);
        });

        Schema::create('task_service', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->primary(['task_id', 'service_id']);
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->unsignedSmallInteger('duration_months')->nullable()->after('end_date');
            $table->string('company_signatory_name')->nullable()->after('counterparty_address');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('recorded_by')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->index('recorded_by');
        });
        Schema::table('incomes', function (Blueprint $table) {
            $table->foreignId('recorded_by')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->index('recorded_by');
        });
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('recorded_by')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->index('recorded_by');
        });
        Schema::table('salaries', function (Blueprint $table) {
            $table->foreignId('recorded_by')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->index('recorded_by');
        });

        Schema::create('stock_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();
            $table->unique(['stock_id', 'employee_id']);
        });

        // Preserve every legacy assignment. A legacy row represented one holder
        // for the whole stock quantity, so its allocation receives that amount.
        DB::table('stocks')->whereNotNull('staff_id')->orderBy('id')->each(function (object $stock): void {
            DB::table('stock_allocations')->insert([
                'stock_id' => $stock->id,
                'employee_id' => $stock->staff_id,
                'quantity' => max(1, (int) $stock->quantity),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('staff_id');
        });

        // Suspended never had its own permissions, expiry, or reinstatement
        // policy. Retain access history by normalizing it to inactive.
        DB::table('users')->where('acc_status', 'suspended')->update(['acc_status' => 'inactive']);

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('tax_number');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('tax_number')->nullable()->after('contract_duration');
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->foreignId('staff_id')->nullable()->after('condition')->constrained('employees')->nullOnDelete();
        });
        Schema::dropIfExists('stock_allocations');

        Schema::table('salaries', fn (Blueprint $table) => $table->dropConstrainedForeignId('recorded_by'));
        Schema::table('expenses', fn (Blueprint $table) => $table->dropConstrainedForeignId('recorded_by'));
        Schema::table('incomes', fn (Blueprint $table) => $table->dropConstrainedForeignId('recorded_by'));
        Schema::table('invoices', fn (Blueprint $table) => $table->dropConstrainedForeignId('recorded_by'));

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['duration_months', 'company_signatory_name']);
        });

        Schema::dropIfExists('task_service');
        Schema::dropIfExists('project_service');
        Schema::dropIfExists('contract_service');

        Schema::table('boards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });
        Schema::dropIfExists('projects');
    }
};
