<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'view_stocks' => 'view_assets',
            'create_stocks' => 'create_assets',
            'edit_stocks' => 'edit_assets',
            'delete_stocks' => 'delete_assets',
        ] as $old => $new) {
            $oldPermission = DB::table('permissions')->where('name', $old)->first();
            $newPermission = DB::table('permissions')->where('name', $new)->first();

            if ($oldPermission && $newPermission) {
                DB::table('role_permissions')->where('permission_id', $oldPermission->id)
                    ->whereNotIn('role_id', function ($query) use ($newPermission) {
                        $query->select('role_id')->from('role_permissions')
                            ->where('permission_id', $newPermission->id);
                    })->update(['permission_id' => $newPermission->id]);
                DB::table('role_permissions')->where('permission_id', $oldPermission->id)->delete();
                DB::table('permissions')->where('id', $oldPermission->id)->delete();
            } elseif ($oldPermission) {
                DB::table('permissions')->where('id', $oldPermission->id)->update(['name' => $new]);
            }
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', 'view_assets')->update(['name' => 'view_stocks']);
        DB::table('permissions')->where('name', 'create_assets')->update(['name' => 'create_stocks']);
        DB::table('permissions')->where('name', 'edit_assets')->update(['name' => 'edit_stocks']);
        DB::table('permissions')->where('name', 'delete_assets')->update(['name' => 'delete_stocks']);
    }
};
