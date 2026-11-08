<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OLD = 'assign_admission_employee';

    private const NEW = 'assign_responsible_employees';

    public function up(): void
    {
        $this->rename(self::OLD, self::NEW);
    }

    public function down(): void
    {
        $this->rename(self::NEW, self::OLD);
    }

    private function rename(string $from, string $to): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $old = DB::table('permissions')->where('name', $from)->first();
        $new = DB::table('permissions')->where('name', $to)->first();

        if (! $old && ! $new) {
            return;
        }

        if ($old && ! $new) {
            DB::table('permissions')->where('id', $old->id)->update([
                'name' => $to,
                'category' => $old->category,
                'updated_at' => now(),
            ]);

            return;
        }

        if (! $old || ! $new || ! Schema::hasTable('role_permissions')) {
            return;
        }

        // Both rows exist (for example a partially seeded database). Move role
        // grants to the canonical row before removing the duplicate.
        $grants = DB::table('role_permissions')->where('permission_id', $old->id)->get();
        foreach ($grants as $grant) {
            DB::table('role_permissions')->insertOrIgnore([
                'role_id' => $grant->role_id,
                'permission_id' => $new->id,
                'created_at' => $grant->created_at ?? now(),
                'updated_at' => $grant->updated_at ?? now(),
            ]);
        }
        DB::table('role_permissions')->where('permission_id', $old->id)->delete();
        DB::table('permissions')->where('id', $old->id)->delete();
    }
};
