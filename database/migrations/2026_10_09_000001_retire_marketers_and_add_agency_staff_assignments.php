<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL commits DDL as it runs, so a failed migration must be resumable.
        foreach (['visitors' => 'counselor_employee_id', 'applicants' => 'admission_employee_id'] as $name => $column) {
            if (! Schema::hasColumn($name, $column)) {
                Schema::table($name, fn (Blueprint $table) => $table->foreignId($column)->nullable());
            }
            $hasForeignKey = collect(Schema::getForeignKeys($name))->contains(fn (array $foreign) => $foreign['columns'] === [$column]);
            if (! $hasForeignKey) {
                Schema::table($name, fn (Blueprint $table) => $table->foreign($column)->references('id')->on('employees')->nullOnDelete());
            }
        }
        // Issued document snapshots and employee identities remain intact.
        foreach (['visitors', 'applicants', 'contracts', 'applicant_contract'] as $name) {
            if (Schema::hasColumn($name, 'marketer_id')) {
                // Some historical schemas have only the column/index, and
                // others use a non-conventional constraint name.
                foreach (Schema::getForeignKeys($name) as $foreign) {
                    if (in_array('marketer_id', $foreign['columns'], true)) {
                        Schema::table($name, fn (Blueprint $table) => $table->dropForeign($foreign['name'] ?? $foreign['columns']));
                    }
                }
                foreach (Schema::getIndexes($name) as $index) {
                    if (in_array('marketer_id', $index['columns'], true)) {
                        Schema::table($name, fn (Blueprint $table) => $table->dropIndex($index['name']));
                    }
                }
                Schema::table($name, fn (Blueprint $table) => $table->dropColumn('marketer_id'));
            }
            if (Schema::hasColumn($name, 'marketer')) {
                Schema::table($name, fn (Blueprint $table) => $table->dropColumn('marketer'));
            }
        }
        Schema::dropIfExists('marketers');
        $permissions = DB::table('permissions')->whereIn('name', ['view_marketers', 'manage_marketers'])->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $permissions)->delete();
        DB::table('permissions')->whereIn('id', $permissions)->delete();
        DB::table('positions')->where(fn ($query) => $query->where('system_key', 'marketer')->orWhereRaw('LOWER(title) = ?', ['marketer']))->update(['system_key' => null, 'title' => 'Relationship Support']);
        foreach (['Counselor' => 'counselor', 'Admission' => 'admission'] as $title => $key) {
            if (! DB::table('positions')->where('system_key', $key)->exists()) {
                DB::table('positions')->insert(['title' => $title, 'system_key' => $key, 'salary' => 0, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        $overviewId = DB::table('permissions')->where('name', 'view_agency_overview')->value('id')
            ?? DB::table('permissions')->insertGetId(['name' => 'view_agency_overview', 'category' => 'educational', 'created_at' => now(), 'updated_at' => now()]);
        $management = DB::table('roles')->whereIn('name', ['super_admin', 'admin', 'manager', 'team_lead', 'senior_counselor'])->pluck('id');
        foreach ($management as $roleId) {
            DB::table('role_permissions')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $overviewId], ['created_at' => now(), 'updated_at' => now()]);
        }
        $visaId = DB::table('permissions')->where('name', 'view_visa_reports')->value('id');
        if ($visaId) {
            DB::table('role_permissions')->where('permission_id', $visaId)->whereNotIn('role_id', $management)->delete();
        }
    }

    public function down(): void
    {
        // Retirement is intentionally irreversible; restoring obsolete records
        // requires a database backup, not fabricated marketer identities.
    }
};
