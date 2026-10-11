<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A board with a non-null `user_id` is a private **personal** board. It belongs
 * to exactly one user and is never shared through board membership, which is
 * what keeps one employee's personal tasks out of everyone else's Kanban.
 * A null `user_id` keeps the previous behaviour: a shared operational board.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('order')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('boards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
