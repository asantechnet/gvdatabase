<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('attendance_id')->unique()->constrained()->restrictOnDelete();
            $table->date('report_date');
            $table->text('summary');
            $table->text('achievements')->nullable();
            $table->text('challenges')->nullable();
            $table->text('next_day_plan')->nullable();
            $table->text('support_needed')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'report_date']);
            $table->index(['report_date', 'submitted_at']);
        });

        Schema::create('monthly_attendance_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('present_days')->default(0);
            $table->unsignedSmallInteger('absent_days')->default(0);
            $table->unsignedSmallInteger('paid_leave_days')->default(0);
            $table->unsignedSmallInteger('incomplete_days')->default(0);
            $table->unsignedInteger('gross_minutes')->default(0);
            $table->unsignedInteger('break_minutes')->default(0);
            $table->unsignedInteger('net_worked_minutes')->default(0);
            $table->unsignedInteger('required_minutes')->default(0);
            $table->unsignedInteger('late_arrival_minutes')->default(0);
            $table->unsignedInteger('early_departure_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->unique(['employee_id', 'year', 'month'], 'monthly_attendance_employee_period_unique');
            $table->index(['year', 'month']);
        });

        Schema::create('daily_analytics', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->unsignedInteger('attendance_present')->default(0);
            $table->unsignedInteger('attendance_absent')->default(0);
            $table->unsignedInteger('attendance_paid_leave')->default(0);
            $table->unsignedInteger('attendance_incomplete')->default(0);
            $table->unsignedBigInteger('worked_minutes')->default(0);
            $table->unsignedBigInteger('overtime_minutes')->default(0);
            $table->decimal('income_total', 20, 4)->default(0);
            $table->decimal('expense_total', 20, 4)->default(0);
            $table->unsignedInteger('tasks_created')->default(0);
            $table->unsignedInteger('tasks_completed')->default(0);
            $table->unsignedInteger('contracts_created')->default(0);
            $table->unsignedInteger('contracts_cancelled')->default(0);
            $table->unsignedInteger('employees_onboarded')->default(0);
            $table->unsignedInteger('bonus_nominations')->default(0);
            $table->unsignedInteger('bonuses_granted')->default(0);
            $table->decimal('bonus_base_total', 20, 4)->default(0);
            $table->timestamp('calculated_at');
            $table->timestamps();
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
    }

    public function down(): void
    {
        Schema::dropIfExists('bonus_rule_sets');
        Schema::dropIfExists('automation_runs');
        Schema::dropIfExists('monthly_analytics');
        Schema::dropIfExists('daily_analytics');
        Schema::dropIfExists('monthly_attendance_summaries');
        Schema::dropIfExists('daily_reports');
    }
};
