<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Visitor enquiry profiles and per-service contract field sets.
 *
 * - `provinces`, `english_levels` and `acquisition_routes` are educational
 *   master-data catalogues used by the visitor form.
 * - `visitors.counselor_employee_id` is renamed to `consultant_employee_id`:
 *   the visitor's assigned advisor is now called a consultant everywhere the
 *   visitor is concerned. The `counselor` position/role vocabulary is unchanged.
 * - `academy_applicant_details.student_id` holds the academy student number.
 * - `applicant_contract.application_fee_min/max` store the private application
 *   fee as a range; `application_fee` keeps the range maximum.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['provinces', 'english_levels', 'acquisition_routes'] as $name) {
            if (! Schema::hasTable($name)) {
                Schema::create($name, function (Blueprint $table) {
                    $table->id();
                    $table->string('name')->unique();
                    $table->text('description')->nullable();
                    $table->unsignedInteger('sort_order')->default(0);
                    $table->boolean('is_active')->default(true);
                    $table->timestamps();
                    $table->softDeletes();
                });
            }
        }

        $visitorForeignKeys = fn (string $column) => collect(Schema::getForeignKeys('visitors'))
            ->filter(fn (array $foreign) => $foreign['columns'] === [$column]);

        if (Schema::hasColumn('visitors', 'counselor_employee_id') && ! Schema::hasColumn('visitors', 'consultant_employee_id')) {
            foreach ($visitorForeignKeys('counselor_employee_id') as $foreign) {
                Schema::table('visitors', fn (Blueprint $table) => $table->dropForeign($foreign['name'] ?? ['counselor_employee_id']));
            }
            Schema::table('visitors', fn (Blueprint $table) => $table->renameColumn('counselor_employee_id', 'consultant_employee_id'));
        }
        // Renaming the column leaves the reference unconstrained; restore the
        // nullable employee foreign key under its new name.
        if (Schema::hasColumn('visitors', 'consultant_employee_id') && $visitorForeignKeys('consultant_employee_id')->isEmpty()) {
            Schema::table('visitors', fn (Blueprint $table) => $table->foreign('consultant_employee_id')->references('id')->on('employees')->nullOnDelete());
        }

        $visitorColumns = [
            'education_level_id' => ['type' => 'foreignId', 'target' => 'education_levels'],
            'province_id' => ['type' => 'foreignId', 'target' => 'provinces'],
            'english_level_id' => ['type' => 'foreignId', 'target' => 'english_levels'],
            'acquisition_route_id' => ['type' => 'foreignId', 'target' => 'acquisition_routes'],
        ];
        foreach ($visitorColumns as $column => $definition) {
            if (! Schema::hasColumn('visitors', $column)) {
                Schema::table('visitors', function (Blueprint $table) use ($column) {
                    $table->foreignId($column)->nullable();
                });
            }
            if ($visitorForeignKeys($column)->isEmpty()) {
                Schema::table('visitors', fn (Blueprint $table) => $table->foreign($column)->references('id')->on($definition['target'])->nullOnDelete());
            }
        }
        if (! Schema::hasColumn('visitors', 'age')) {
            Schema::table('visitors', fn (Blueprint $table) => $table->unsignedTinyInteger('age')->nullable());
        }
        if (! Schema::hasColumn('visitors', 'grade_score')) {
            Schema::table('visitors', fn (Blueprint $table) => $table->decimal('grade_score', 5, 2)->nullable());
        }
        if (! Schema::hasColumn('visitors', 'reviewed_by_consultant')) {
            Schema::table('visitors', function (Blueprint $table) {
                $table->boolean('reviewed_by_consultant')->default(false);
                $table->index('reviewed_by_consultant', 'visitors_reviewed_by_consultant_index');
            });
        }

        if (! Schema::hasColumn('academy_applicant_details', 'student_id')) {
            Schema::table('academy_applicant_details', fn (Blueprint $table) => $table->string('student_id', 64)->nullable());
        }

        foreach (['application_fee_min', 'application_fee_max'] as $column) {
            if (! Schema::hasColumn('applicant_contract', $column)) {
                Schema::table('applicant_contract', fn (Blueprint $table) => $table->decimal($column, 20, 2)->nullable());
            }
        }

        $this->seedPermissions();
    }

    public function down(): void
    {
        foreach (['application_fee_min', 'application_fee_max'] as $column) {
            if (Schema::hasColumn('applicant_contract', $column)) {
                Schema::table('applicant_contract', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
        if (Schema::hasColumn('academy_applicant_details', 'student_id')) {
            Schema::table('academy_applicant_details', fn (Blueprint $table) => $table->dropColumn('student_id'));
        }
        if (Schema::hasColumn('visitors', 'reviewed_by_consultant')) {
            Schema::table('visitors', fn (Blueprint $table) => $table->dropColumn('reviewed_by_consultant'));
        }
        foreach (['grade_score', 'age'] as $column) {
            if (Schema::hasColumn('visitors', $column)) {
                Schema::table('visitors', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
        $visitorForeignKeys = fn (string $column) => collect(Schema::getForeignKeys('visitors'))
            ->filter(fn (array $foreign) => $foreign['columns'] === [$column]);
        foreach (['education_level_id', 'province_id', 'english_level_id', 'acquisition_route_id'] as $column) {
            if (! Schema::hasColumn('visitors', $column)) {
                continue;
            }
            foreach ($visitorForeignKeys($column) as $foreign) {
                Schema::table('visitors', fn (Blueprint $table) => $table->dropForeign($foreign['name'] ?? [$column]));
            }
            Schema::table('visitors', fn (Blueprint $table) => $table->dropColumn($column));
        }
        if (Schema::hasColumn('visitors', 'consultant_employee_id') && ! Schema::hasColumn('visitors', 'counselor_employee_id')) {
            foreach ($visitorForeignKeys('consultant_employee_id') as $foreign) {
                Schema::table('visitors', fn (Blueprint $table) => $table->dropForeign($foreign['name'] ?? ['consultant_employee_id']));
            }
            Schema::table('visitors', fn (Blueprint $table) => $table->renameColumn('consultant_employee_id', 'counselor_employee_id'));
            Schema::table('visitors', fn (Blueprint $table) => $table->foreign('counselor_employee_id')->references('id')->on('employees')->nullOnDelete());
        }
        Schema::dropIfExists('acquisition_routes');
        Schema::dropIfExists('english_levels');
        Schema::dropIfExists('provinces');
        // Permission configuration is intentionally kept; removing it would
        // destroy later custom grants.
    }

    private function seedPermissions(): void
    {
        $permissions = [
            ['name' => 'review_visitors', 'category' => 'operations'],
        ];
        foreach (['province', 'english_level', 'acquisition_route'] as $catalog) {
            foreach (['view', 'create', 'edit', 'delete'] as $action) {
                $permissions[] = ['name' => "{$action}_{$catalog}", 'category' => 'educational_visa'];
            }
        }

        foreach ($permissions as $permission) {
            DB::table('permissions')->insertOrIgnore([
                ...$permission,
                'guard_name' => 'api',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $ids = DB::table('permissions')->whereIn('name', array_column($permissions, 'name'))
            ->whereNull('deleted_at')->pluck('id', 'name');
        $roles = DB::table('roles')->whereIn('name', ['admin', 'manager', 'staff', 'counselor', 'senior_counselor'])
            ->pluck('id', 'name');

        $grants = [
            'review_visitors' => ['admin', 'manager'],
        ];
        foreach (['province', 'english_level', 'acquisition_route'] as $catalog) {
            $grants["view_{$catalog}"] = ['admin', 'manager', 'staff', 'counselor', 'senior_counselor'];
            $grants["create_{$catalog}"] = ['admin', 'manager'];
            $grants["edit_{$catalog}"] = ['admin', 'manager'];
            $grants["delete_{$catalog}"] = ['admin'];
        }

        foreach ($grants as $permission => $roleNames) {
            $permissionId = $ids[$permission] ?? null;
            if (! $permissionId) {
                continue;
            }
            foreach ($roleNames as $roleName) {
                $roleId = $roles[$roleName] ?? null;
                if ($roleId) {
                    DB::table('role_permissions')->insertOrIgnore([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
};
