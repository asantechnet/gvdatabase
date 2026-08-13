<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('default_break_minutes')->default(0)->after('office_close_time');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('office_id')->nullable()->after('position_id')->constrained()->nullOnDelete();
            $table->decimal('total_worked_hours', 14, 2)->default(0)->after('salary');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('office_id')->nullable()->after('employee_id')->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('break_minutes')->nullable()->after('checked_out_at');
            $table->unsignedInteger('gross_minutes')->nullable()->after('break_minutes');
            $table->unsignedInteger('net_worked_minutes')->nullable()->after('gross_minutes');
            $table->unsignedInteger('required_minutes')->nullable()->after('net_worked_minutes');
            $table->unsignedInteger('late_arrival_minutes')->default(0)->after('required_minutes');
            $table->unsignedInteger('early_departure_minutes')->default(0)->after('late_arrival_minutes');
            $table->unsignedInteger('overtime_minutes')->default(0)->after('early_departure_minutes');
            $table->string('missing_punch_state', 20)->default('none')->after('overtime_minutes');
            $table->boolean('is_overnight_shift')->default(false)->after('missing_punch_state');
            $table->string('calculation_hash', 64)->nullable()->after('is_overnight_shift');
            $table->timestamp('calculated_at')->nullable()->after('calculation_hash');
            $table->index(['date', 'office_id', 'status']);
            $table->index(['calculated_at', 'calculation_hash']);
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
            $table->timestamp('calculated_at');
            $table->timestamps();
        });

        Schema::table('worthy_employees', function (Blueprint $table) {
            $table->foreignId('source_task_id')->nullable()->after('employee_id')->constrained('tasks')->nullOnDelete();
            $table->boolean('is_automatic')->default(false)->after('source_task_id');
            $table->unique(['employee_id', 'source_task_id'], 'worthy_employee_task_unique');
        });
    }

    public function down(): void
    {
        Schema::table('worthy_employees', function (Blueprint $table) {
            $table->dropUnique('worthy_employee_task_unique');
            $table->dropConstrainedForeignId('source_task_id');
            $table->dropColumn('is_automatic');
        });
        Schema::dropIfExists('daily_analytics');
        Schema::dropIfExists('monthly_attendance_summaries');

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex(['date', 'office_id', 'status']);
            $table->dropIndex(['calculated_at', 'calculation_hash']);
            $table->dropConstrainedForeignId('office_id');
            $table->dropColumn(['break_minutes', 'gross_minutes', 'net_worked_minutes', 'required_minutes', 'late_arrival_minutes', 'early_departure_minutes', 'overtime_minutes', 'missing_punch_state', 'is_overnight_shift', 'calculation_hash', 'calculated_at']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('office_id');
            $table->dropColumn('total_worked_hours');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('default_break_minutes');
        });
    }
};
