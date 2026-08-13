<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bonus_rule_sets', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Default task bonus rules');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->json('rules');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        DB::table('bonus_rule_sets')->insert([
            'name' => 'Default task bonus rules', 'version' => 1, 'is_active' => true,
            'rules' => json_encode([
                'completed_task_points' => 10, 'priority_weights' => ['low' => .75, 'medium' => 1, 'high' => 1.5, 'urgent' => 2],
                'before_deadline_points' => 2, 'after_deadline_points' => -2, 'reopened_penalty' => -3, 'rejected_penalty' => -5,
                'default_quality_score' => 3, 'quality_multiplier_enabled' => true, 'minimum_participation_percent' => 10,
                'minimum_completed_tasks' => 1, 'monthly_score_threshold' => 10, 'amount_per_point' => 10,
                'maximum_monthly_bonus' => 1000, 'excluded_employee_ids' => [], 'excluded_departments' => [],
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Schema::table('tasks', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('due_date');
            $table->unsignedSmallInteger('reopened_count')->default(0)->after('completed_at');
            $table->unsignedSmallInteger('rejected_count')->default(0)->after('reopened_count');
            $table->decimal('manager_quality_score', 3, 2)->nullable()->after('rejected_count');
            $table->foreignId('quality_reviewed_by')->nullable()->after('manager_quality_score')->constrained('users')->nullOnDelete();
            $table->timestamp('quality_reviewed_at')->nullable()->after('quality_reviewed_by');
            $table->index(['completed_at', 'progress']);
        });
        Schema::table('task_user', function (Blueprint $table) {
            $table->decimal('contribution_percent', 5, 2)->nullable();
        });
        DB::table('tasks')->where('progress', 100)->whereNull('completed_at')->update(['completed_at' => DB::raw('updated_at')]);

        Schema::table('worthy_employees', function (Blueprint $table) {
            $table->string('calculation_key', 80)->nullable()->unique()->after('is_automatic');
            $table->decimal('calculation_score', 12, 4)->nullable()->after('calculation_key');
            $table->json('calculation_inputs')->nullable()->after('calculation_score');
            $table->json('rule_snapshot')->nullable()->after('calculation_inputs');
            $table->timestamp('calculated_at')->nullable()->after('rule_snapshot');
            $table->timestamp('granted_at')->nullable()->after('calculated_at');
            $table->foreignId('granted_by')->nullable()->after('granted_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('is_cancelled');
        });
        DB::table('contracts')->where('is_cancelled', true)->whereNull('cancelled_at')->update(['cancelled_at' => DB::raw('updated_at')]);

        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->string('job', 80);
            $table->string('period_key', 100);
            $table->string('status', 20)->default('running');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->json('parameters')->nullable();
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->text('error_trace')->nullable();
            $table->timestamps();
            $table->unique(['job', 'period_key']);
            $table->index(['status', 'started_at']);
        });

        Schema::table('daily_analytics', function (Blueprint $table) {
            $table->unsignedInteger('tasks_created')->default(0);
            $table->unsignedInteger('tasks_completed')->default(0);
            $table->unsignedInteger('contracts_created')->default(0);
            $table->unsignedInteger('contracts_cancelled')->default(0);
            $table->unsignedInteger('employees_onboarded')->default(0);
            $table->unsignedInteger('bonus_nominations')->default(0);
            $table->unsignedInteger('bonuses_granted')->default(0);
            $table->decimal('bonus_base_total', 20, 4)->default(0);
        });

        Schema::create('monthly_analytics', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedBigInteger('attendance_present')->default(0);
            $table->unsignedBigInteger('attendance_absent')->default(0);
            $table->unsignedBigInteger('attendance_paid_leave')->default(0);
            $table->unsignedBigInteger('attendance_incomplete')->default(0);
            $table->unsignedBigInteger('worked_minutes')->default(0);
            $table->unsignedBigInteger('overtime_minutes')->default(0);
            $table->decimal('income_total', 20, 4)->default(0);
            $table->decimal('expense_total', 20, 4)->default(0);
            $table->unsignedBigInteger('tasks_created')->default(0);
            $table->unsignedBigInteger('tasks_completed')->default(0);
            $table->unsignedBigInteger('contracts_created')->default(0);
            $table->unsignedBigInteger('contracts_cancelled')->default(0);
            $table->unsignedBigInteger('employees_onboarded')->default(0);
            $table->unsignedBigInteger('bonus_nominations')->default(0);
            $table->unsignedBigInteger('bonuses_granted')->default(0);
            $table->decimal('bonus_base_total', 20, 4)->default(0);
            $table->timestamp('calculated_at');
            $table->timestamps();
            $table->unique(['year', 'month']);
        });

        $now = now();
        foreach ([['manage_bonus_rules', 'human_resources'], ['view_automation_runs', 'core'], ['view_analytics', 'core']] as [$name, $category]) {
            DB::table('permissions')->updateOrInsert(['name' => $name], ['category' => $category, 'created_at' => $now, 'updated_at' => $now]);
        }
        $permissionIds = DB::table('permissions')->whereIn('name', ['view_automation_runs', 'view_analytics'])->pluck('id');
        foreach ($permissionIds as $permissionId) {
            DB::table('role_permissions')->updateOrInsert(['role' => 'manager', 'permission_id' => $permissionId], ['created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('name', ['manage_bonus_rules', 'view_automation_runs', 'view_analytics'])->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        Schema::dropIfExists('monthly_analytics');
        Schema::table('daily_analytics', fn (Blueprint $table) => $table->dropColumn(['tasks_created', 'tasks_completed', 'contracts_created', 'contracts_cancelled', 'employees_onboarded', 'bonus_nominations', 'bonuses_granted', 'bonus_base_total']));
        Schema::dropIfExists('automation_runs');
        Schema::table('contracts', fn (Blueprint $table) => $table->dropColumn('cancelled_at'));
        Schema::table('worthy_employees', function (Blueprint $table) {
            $table->dropUnique(['calculation_key']);
            $table->dropConstrainedForeignId('granted_by');
            $table->dropColumn(['calculation_key', 'calculation_score', 'calculation_inputs', 'rule_snapshot', 'calculated_at', 'granted_at']);
        });
        Schema::table('task_user', fn (Blueprint $table) => $table->dropColumn('contribution_percent'));
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['completed_at', 'progress']);
            $table->dropConstrainedForeignId('quality_reviewed_by');
            $table->dropColumn(['completed_at', 'reopened_count', 'rejected_count', 'manager_quality_score', 'quality_reviewed_at']);
        });
        Schema::dropIfExists('bonus_rule_sets');
    }
};
