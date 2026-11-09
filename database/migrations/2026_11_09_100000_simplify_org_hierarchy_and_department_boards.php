<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This migration was partially applied manually in a development
        // database before it was recorded. Every structural change is guarded
        // so it can be safely re-run on such a database.
        if (! Schema::hasColumn('roles', 'is_senior')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->boolean('is_senior')->default(false)->after('level')->index();
            });
        }

        DB::table('roles')->where('level', '>=', 220)->update(['is_senior' => true]);

        if (! Schema::hasTable('department_manager')) {
            Schema::create('department_manager', function (Blueprint $table) {
                $table->foreignId('department_id')->constrained()->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->timestamps();

                $table->primary(['department_id', 'employee_id']);
            });
        }

        DB::table('departments')
            ->whereNotNull('manager_employee_id')
            ->orderBy('id')
            ->each(function ($department): void {
                DB::table('department_manager')->insertOrIgnore([
                    'department_id' => $department->id,
                    'employee_id' => $department->manager_employee_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        if (! Schema::hasColumn('boards', 'department_id')) {
            Schema::table('boards', function (Blueprint $table) {
                $table->foreignId('department_id')->nullable()->after('user_id')
                    ->constrained('departments')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('tasks', 'created_by')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->foreignId('created_by')->nullable()->after('board_id')
                    ->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('comment_mentions')) {
            Schema::create('comment_mentions', function (Blueprint $table) {
                $table->foreignId('comment_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->primary(['comment_id', 'user_id']);
            });
        }

        // The old reporting line and primary-department projection are no
        // longer part of the model. Memberships are equal; managers live in
        // department_manager.
        if (Schema::hasColumn('department_employee', 'is_primary')) {
            // Do not drop the composite index first: MySQL uses it to support
            // the department foreign key. Dropping the column rewrites the
            // index to its remaining department_id column.
            Schema::table('department_employee', function (Blueprint $table) {
                $table->dropColumn('is_primary');
            });
        }

        $foreignKeys = $this->foreignKeyNames('employees');

        if ($foreignKeys->contains('employees_supervisor_id_foreign')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropForeign(['supervisor_id']);
            });
        }

        if ($foreignKeys->contains('employees_department_id_foreign')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropForeign(['department_id']);
            });
        }

        $obsoleteEmployeeColumns = array_values(array_filter(
            ['supervisor_id', 'department_id', 'department'],
            fn (string $column) => Schema::hasColumn('employees', $column),
        ));

        if ($obsoleteEmployeeColumns !== []) {
            Schema::table('employees', function (Blueprint $table) use ($obsoleteEmployeeColumns) {
                $table->dropColumn($obsoleteEmployeeColumns);
            });
        }

        $permissionId = DB::table('permissions')
            ->where('name', 'assign_employee_supervisor')
            ->value('id');

        if ($permissionId !== null) {
            DB::table('role_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('employees', 'supervisor_id')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->foreignId('supervisor_id')->nullable()->after('office_id')->constrained('employees')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('employees', 'department_id')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->foreignId('department_id')->nullable()->after('office_id')->constrained('departments')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('employees', 'department')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->string('department')->nullable();
            });
        }

        if (! Schema::hasColumn('department_employee', 'is_primary')) {
            Schema::table('department_employee', function (Blueprint $table) {
                $table->boolean('is_primary')->default(false);
                $table->index(['department_id', 'is_primary']);
            });
        }

        Schema::dropIfExists('comment_mentions');

        if (Schema::hasColumn('tasks', 'created_by')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropConstrainedForeignId('created_by');
            });
        }

        if (Schema::hasColumn('boards', 'department_id')) {
            Schema::table('boards', function (Blueprint $table) {
                $table->dropConstrainedForeignId('department_id');
            });
        }

        Schema::dropIfExists('department_manager');

        if (Schema::hasColumn('roles', 'is_senior')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropColumn('is_senior');
            });
        }
    }

    /** @return Collection<int, string> */
    private function foreignKeyNames(string $table): Collection
    {
        return collect(Schema::getForeignKeys($table))->pluck('name');
    }
};
