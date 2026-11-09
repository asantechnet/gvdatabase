<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Academy staff are employees with a teacher position or role. The old
 * `teachers` catalogue is backfilled into employee rows before its pivots are
 * remapped, then the time, duration and teacher catalogues are removed.
 *
 * The same migration records the teacher-commission trigger (`completed_at`)
 * and links each class accrual to the class that earned it.
 */
return new class extends Migration
{
    public function up(): void
    {
        $teacherPosition = DB::table('positions')->where('system_key', 'teacher')->first();
        if (! $teacherPosition) {
            DB::table('positions')->insert([
                'title' => 'Teacher',
                'description' => 'Teaches Academy classes',
                'salary' => 0,
                'status' => 'active',
                'system_key' => 'teacher',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $teacherPosition = DB::table('positions')->where('system_key', 'teacher')->first();
        }

        $teacherRole = DB::table('roles')->where('name', 'teacher')->first();
        if (! $teacherRole) {
            DB::table('roles')->insert([
                'name' => 'teacher',
                'level' => 40,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $teacherRole = DB::table('roles')->where('name', 'teacher')->first();
        }

        $viewAttendance = DB::table('permissions')->where('name', 'view_academy_attendance')->first();
        if (! $viewAttendance) {
            DB::table('permissions')->insert([
                'name' => 'view_academy_attendance',
                'category' => 'educational_visa',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $viewAttendance = DB::table('permissions')->where('name', 'view_academy_attendance')->first();
        }

        // Everyone who could already manage attendance can also open it.
        $attendanceRoleIds = DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->whereIn('permissions.name', ['manage_academy_attendance', 'view_academy_contracts'])
            ->pluck('role_permissions.role_id')
            ->push($teacherRole->id)
            ->unique();

        foreach ($attendanceRoleIds as $roleId) {
            DB::table('role_permissions')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $viewAttendance->id,
            ]);
        }

        $manageAttendance = DB::table('permissions')->where('name', 'manage_academy_attendance')->first();
        if ($manageAttendance) {
            DB::table('role_permissions')->insertOrIgnore([
                'role_id' => $teacherRole->id,
                'permission_id' => $manageAttendance->id,
            ]);
        }

        // The old catalogue permissions have no surface left.
        $retired = DB::table('permissions')->whereIn('name', [
            'view_teacher', 'create_teacher', 'edit_teacher', 'delete_teacher',
            'view_time', 'create_time', 'edit_time', 'delete_time',
            'view_duration', 'create_duration', 'edit_duration', 'delete_duration',
        ])->pluck('id');
        if ($retired->isNotEmpty()) {
            DB::table('role_permissions')->whereIn('permission_id', $retired)->delete();
            DB::table('permissions')->whereIn('id', $retired)->delete();
        }

        $teacherMap = [];
        if (Schema::hasTable('teachers')) {
            foreach (DB::table('teachers')->orderBy('id')->get() as $teacher) {
                $employeeId = DB::table('employees')
                    ->where('name', $teacher->name)
                    ->whereNull('deleted_at')
                    ->value('id');

                if (! $employeeId) {
                    $employeeId = DB::table('employees')->insertGetId([
                        'name' => $teacher->name,
                        'father_name' => '-',
                        'contact' => $teacher->contact ?: '-',
                        'guarantor_name' => null,
                        'education_lvl' => '-',
                        'position_id' => $teacherPosition->id,
                        'addr' => '-',
                        'join_date' => now()->toDateString(),
                        'contract_duration' => 'n/a',
                        'status' => 'active',
                        'is_commission_based' => false,
                        'created_at' => $teacher->created_at ?? now(),
                        'updated_at' => $teacher->updated_at ?? now(),
                    ]);
                }

                $teacherMap[(int) $teacher->id] = (int) $employeeId;
            }

            $this->remapTeacherPivot('academy_class_teacher', $teacherMap);
            $this->remapTeacherPivot('academy_applicant_teacher', $teacherMap);
            $this->remapTeacherPivot('academy_teacher_timesheets', $teacherMap);
        }

        foreach ([
            'academy_classes' => ['time_id', 'duration_id'],
            'academy_enrollments' => ['time_id', 'duration_id'],
            'academy_applicant_details' => ['time_id', 'duration_id'],
        ] as $table => $columns) {
            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropConstrainedForeignId($column));
                }
            }
        }

        Schema::dropIfExists('times');
        Schema::dropIfExists('durations');
        Schema::dropIfExists('teachers');

        if (! Schema::hasColumn('academy_classes', 'completed_at')) {
            Schema::table('academy_classes', fn (Blueprint $table) => $table->timestamp('completed_at')->nullable()->after('ends_on'));
        }

        if (! Schema::hasColumn('commission_accruals', 'academy_class_id')) {
            Schema::table('commission_accruals', function (Blueprint $table): void {
                $table->foreignId('academy_class_id')->nullable()->constrained('academy_classes')->nullOnDelete();
                $table->unique(['academy_class_id', 'employee_id'], 'commission_accruals_class_employee_unique');
            });
        }
    }

    /** @param array<int, int> $map */
    private function remapTeacherPivot(string $table, array $map): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'teacher_id')) {
            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropForeign(['teacher_id']));

        foreach ($map as $oldId => $employeeId) {
            DB::table($table)->where('teacher_id', $oldId)->update(['teacher_id' => $employeeId]);
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->foreign('teacher_id')->references('id')->on('employees')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // The dropped catalogues carried data that cannot be reconstructed.
    }
};
