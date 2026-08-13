<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Positions describe jobs. Access permissions belong to account roles,
        // so the legacy position-to-permission pivot must not remain in use.
        Schema::dropIfExists('permission_position');

        if (Schema::hasColumn('positions', 'is_sys_user')) {
            Schema::table('positions', fn (Blueprint $table) => $table->dropColumn('is_sys_user'));
        }
    }

    public function down(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->boolean('is_sys_user')->default(false)->after('status');
        });

        Schema::create('permission_position', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_id')->constrained()->onDelete('cascade');
            $table->foreignId('position_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            $table->unique(['permission_id', 'position_id']);
        });
    }
};
