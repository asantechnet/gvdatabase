<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Introduces the organisational spine the visa firm works with:
 *
 * - `roles.level` ranks roles so seniority (and the read/write gap between
 *   a senior counselor and a counselor) is a first-class property.
 * - `departments` groups staff; a department names its manager.
 * - `employees.department_id` places a person in that group.
 * - `employees.supervisor_id` assigns a person to their team senior/manager.
 *
 * The legacy free-text `employees.department` column is backfilled into the
 * new table and kept in sync so contract placeholders keep resolving.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->unsignedInteger('level')->default(0)->after('name')->index();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 40)->nullable()->unique();
            $table->text('description')->nullable();
            $table->foreignId('manager_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('department')->constrained('departments')->nullOnDelete();
            $table->foreignId('supervisor_id')->nullable()->after('department_id')->constrained('employees')->nullOnDelete();
        });

        // Backfill one department per distinct legacy free-text value. Rows
        // with no department keep a null department_id and keep working.
        DB::table('employees')
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->select('department')
            ->distinct()
            ->orderBy('department')
            ->pluck('department')
            ->each(function (string $name): void {
                $departmentId = DB::table('departments')->insertGetId([
                    'name' => $name,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('employees')->where('department', $name)->update(['department_id' => $departmentId]);
            });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supervisor_id');
            $table->dropConstrainedForeignId('department_id');
        });

        Schema::dropIfExists('departments');

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('level');
        });
    }
};
