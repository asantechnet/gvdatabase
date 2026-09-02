<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->decimal('leave_duration_days', 3, 1)->default(0)->after('status');
            $table->decimal('paid_leave_days', 3, 1)->default(0)->after('leave_duration_days');
            $table->decimal('unpaid_leave_days', 3, 1)->default(0)->after('paid_leave_days');
        });

        Schema::table('salaries', function (Blueprint $table) {
            $table->decimal('worked_days', 5, 1)->default(30)->change();
            $table->decimal('paid_leave_days', 5, 1)->default(0)->change();
            $table->decimal('absent_days', 5, 1)->default(0)->change();
            $table->decimal('unpaid_leave_days', 5, 1)->default(0)->change();
        });

        Schema::table('monthly_attendance_summaries', function (Blueprint $table) {
            $table->decimal('present_days', 5, 1)->default(0)->change();
            $table->decimal('absent_days', 5, 1)->default(0)->change();
            $table->decimal('paid_leave_days', 5, 1)->default(0)->change();
            $table->decimal('unpaid_leave_days', 5, 1)->default(0)->after('paid_leave_days');
        });

        DB::table('attendances')->where('status', 'paid-leave')->update([
            'leave_duration_days' => 1, 'paid_leave_days' => 1,
        ]);
        DB::table('attendances')->where('status', 'unpaid-leave')->update([
            'leave_duration_days' => 1, 'unpaid_leave_days' => 1,
        ]);

        DB::table('leaves')->where('is_granted', true)->orderBy('id')->each(function ($leave): void {
            $duration = (float) ($leave->duration_days ?? 1);
            $paid = min($duration, max(0, (float) ($leave->paid_days ?? ($leave->is_paid ? $duration : 0))));
            DB::table('attendances')->where('employee_id', $leave->employee_id)->whereDate('date', $leave->date)->update([
                'leave_duration_days' => $duration,
                'paid_leave_days' => $paid,
                'unpaid_leave_days' => max(0, $duration - $paid),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('monthly_attendance_summaries', function (Blueprint $table) {
            $table->dropColumn('unpaid_leave_days');
            $table->unsignedSmallInteger('present_days')->default(0)->change();
            $table->unsignedSmallInteger('absent_days')->default(0)->change();
            $table->unsignedSmallInteger('paid_leave_days')->default(0)->change();
        });
        Schema::table('salaries', function (Blueprint $table) {
            $table->integer('worked_days')->default(30)->change();
            $table->integer('paid_leave_days')->default(0)->change();
            $table->integer('absent_days')->default(0)->change();
            $table->integer('unpaid_leave_days')->default(0)->change();
        });
        Schema::table('attendances', fn (Blueprint $table) => $table->dropColumn([
            'leave_duration_days', 'paid_leave_days', 'unpaid_leave_days',
        ]));
    }
};
