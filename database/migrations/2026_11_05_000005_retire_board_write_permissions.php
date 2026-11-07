<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<string> */
    private const PERMISSIONS = [
        'create_boards',
        'edit_assigned_boards',
        'edit_all_boards',
        'delete_assigned_boards',
        'delete_all_boards',
        'reorder_boards',
        'assign_board_members',
    ];

    public function up(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', self::PERMISSIONS)
            ->pluck('id');

        if ($permissionIds->isEmpty()) {
            return;
        }

        DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }

    public function down(): void
    {
        // Board writes are intentionally not restorable. Re-adding the
        // permissions without the controller/policy surface would be a
        // misleading grant.
    }
};
