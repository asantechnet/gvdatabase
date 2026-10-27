<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HR simplification: attendance has three statuses (present, absent, off_day),
 * leave requests are classified by the employee allowance instead of a
 * paid/unpaid attendance status, and payroll snapshots only expose worked,
 * off-day and absent day counts.
 *
 * The migration copies the old values before dropping the paid-oriented
 * columns so existing development data is not silently lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('missing_attendance_status', 20)->default('absent')->after('default_break_minutes');
        });

        // The company-wide value is a catalogue default copied into a new
        // employee profile; the per-employee allowance is the accountable one.
        Schema::table('settings', function (Blueprint $table) {
            $table->renameColumn('paid_leave_days_per_year', 'off_day_allowance_days_per_year');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->renameColumn('paid_leave_days', 'off_day_allowance_days');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->string('status_simplified', 20)->default('present')->after('date');
            $table->unsignedBigInteger('leave_request_id')->nullable()->after('off_day_id');
            $table->decimal('off_day_days', 3, 1)->default(0)->after('off_day_id');
            $table->string('source', 30)->nullable()->after('logged_at');
            $table->index('leave_request_id', 'attendances_leave_request_id_index');
        });

        DB::table('attendances')->update([
            'status_simplified' => DB::raw("CASE
                WHEN status = 'paid-leave' THEN 'off_day'
                WHEN status = 'unpaid-leave' THEN 'absent'
                ELSE status
            END"),
            'off_day_days' => DB::raw("CASE
                WHEN status = 'paid-leave' THEN leave_duration_days
                WHEN status = 'present' THEN paid_leave_days
                ELSE 0
            END"),
            'source' => DB::raw("CASE
                WHEN off_day_id IS NOT NULL THEN 'holiday'
                WHEN status IN ('paid-leave', 'unpaid-leave') THEN 'leave'
                WHEN checked_in_at IS NOT NULL THEN 'self_service'
                ELSE 'manual'
            END"),
        ]);

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('attendances_date_office_id_status_index');
            $table->dropColumn(['paid_leave_days', 'unpaid_leave_days', 'status']);
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->renameColumn('status_simplified', 'status');
            $table->renameColumn('leave_duration_days', 'leave_days');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->index(['date', 'office_id', 'status'], 'attendances_date_office_id_status_index');
        });

        Schema::table('leaves', function (Blueprint $table) {
            $table->decimal('off_day_days', 3, 1)->nullable()->after('duration_days');
            $table->boolean('off_day_override')->default(false)->after('off_day_days');
        });

        DB::table('leaves')->update([
            'off_day_days' => DB::raw("COALESCE(paid_days, CASE WHEN is_paid = 1 THEN duration_days ELSE 0 END)"),
            'off_day_override' => DB::raw('COALESCE(paid_override, 0)'),
        ]);

        Schema::table('leaves', function (Blueprint $table) {
            $table->dropColumn(['is_paid', 'paid_days', 'paid_override']);
        });

        DB::table('salaries')->update([
            'off_days' => DB::raw('off_days + paid_leave_days'),
        ]);

        Schema::table('salaries', function (Blueprint $table) {
            $table->dropColumn(['paid_leave_days', 'unpaid_leave_days']);
        });

        DB::table('monthly_attendance_summaries')->update([
            'off_days' => DB::raw('off_days + paid_leave_days'),
        ]);

        Schema::table('monthly_attendance_summaries', function (Blueprint $table) {
            $table->dropColumn(['paid_leave_days', 'unpaid_leave_days']);
        });

        Schema::table('daily_analytics', function (Blueprint $table) {
            $table->renameColumn('attendance_paid_leave', 'attendance_off_day');
        });

        Schema::table('monthly_analytics', function (Blueprint $table) {
            $table->renameColumn('attendance_paid_leave', 'attendance_off_day');
        });

        Schema::table('offices', function (Blueprint $table) {
            $table->dropColumn('paid_leave_days_per_year');
        });

        $this->renamePermissions();
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->renameColumn('off_day_allowance_days', 'paid_leave_days');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->renameColumn('off_day_allowance_days_per_year', 'paid_leave_days_per_year');
            $table->dropColumn('missing_attendance_status');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->enum('status_legacy', ['present', 'absent', 'paid-leave', 'unpaid-leave'])->default('present')->after('date');
            $table->decimal('leave_duration_days', 3, 1)->default(0.0)->after('status_legacy');
            $table->decimal('paid_leave_days', 3, 1)->default(0.0)->after('leave_duration_days');
            $table->decimal('unpaid_leave_days', 3, 1)->default(0.0)->after('paid_leave_days');
        });

        DB::table('attendances')->update([
            'status_legacy' => DB::raw("CASE
                WHEN status = 'off_day' THEN 'paid-leave'
                ELSE status
            END"),
            'leave_duration_days' => DB::raw('leave_days'),
            'paid_leave_days' => DB::raw('off_day_days'),
            'unpaid_leave_days' => DB::raw("CASE
                WHEN status = 'absent' THEN GREATEST(leave_days - off_day_days, 0)
                ELSE 0
            END"),
        ]);

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('attendances_date_office_id_status_index');
            $table->dropIndex('attendances_leave_request_id_index');
            $table->dropColumn(['status', 'leave_days', 'off_day_days', 'leave_request_id', 'source']);
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->renameColumn('status_legacy', 'status');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->index(['date', 'office_id', 'status'], 'attendances_date_office_id_status_index');
        });

        Schema::table('leaves', function (Blueprint $table) {
            $table->boolean('is_paid')->nullable()->after('is_granted');
            $table->decimal('paid_days', 3, 1)->nullable()->after('is_paid');
            $table->boolean('paid_override')->default(false)->after('paid_days');
        });

        DB::table('leaves')->update([
            'is_paid' => DB::raw('CASE WHEN off_day_days >= duration_days THEN 1 ELSE 0 END'),
            'paid_days' => DB::raw('off_day_days'),
            'paid_override' => DB::raw('off_day_override'),
        ]);

        Schema::table('leaves', function (Blueprint $table) {
            $table->dropColumn(['off_day_days', 'off_day_override']);
        });

        Schema::table('salaries', function (Blueprint $table) {
            $table->decimal('paid_leave_days', 5, 1)->default(0.0)->after('worked_days');
            $table->decimal('unpaid_leave_days', 5, 1)->default(0.0)->after('absent_days');
        });

        Schema::table('monthly_attendance_summaries', function (Blueprint $table) {
            $table->decimal('paid_leave_days', 5, 1)->default(0.0)->after('absent_days');
            $table->decimal('unpaid_leave_days', 5, 1)->default(0.0)->after('paid_leave_days');
        });

        Schema::table('daily_analytics', function (Blueprint $table) {
            $table->renameColumn('attendance_off_day', 'attendance_paid_leave');
        });

        Schema::table('monthly_analytics', function (Blueprint $table) {
            $table->renameColumn('attendance_off_day', 'attendance_paid_leave');
        });

        Schema::table('offices', function (Blueprint $table) {
            $table->unsignedSmallInteger('paid_leave_days_per_year')->nullable()->after('geofence_enabled');
        });

        $this->renamePermissions(true);
    }

    private function renamePermissions(bool $reverse = false): void
    {
        $map = [
            'view_paid_time_off' => 'view_leave_requests',
            'create_paid_time_off' => 'create_leave_requests',
            'edit_paid_time_off' => 'edit_leave_requests',
            'delete_paid_time_off' => 'delete_leave_requests',
            'request_own_paid_time_off' => 'request_own_leave',
            'grant_paid_time_off' => 'grant_leave_requests',
            'print_paid_time_off' => 'print_leave_requests',
        ];

        if ($reverse) {
            $map = array_flip($map);
        }

        foreach ($map as $old => $new) {
            $oldId = DB::table('permissions')->where('name', $old)->value('id');
            $newId = DB::table('permissions')->where('name', $new)->value('id');

            if ($oldId && ! $newId) {
                DB::table('permissions')->where('id', $oldId)->update(['name' => $new, 'updated_at' => now()]);
            } elseif ($oldId && $newId) {
                DB::table('role_permissions')
                    ->where('permission_id', $oldId)
                    ->get()
                    ->each(fn ($grant) => DB::table('role_permissions')->updateOrInsert(
                        ['role_id' => $grant->role_id, 'permission_id' => $newId],
                        ['created_at' => $grant->created_at ?? now(), 'updated_at' => now()],
                    ));
                DB::table('role_permissions')->where('permission_id', $oldId)->delete();
                DB::table('permissions')->where('id', $oldId)->delete();
            }
        }

        if (! $reverse) {
            DB::table('permissions')->updateOrInsert(
                ['name' => 'edit_own_employee_profile'],
                [
                    'category' => 'human_resources',
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }
};
