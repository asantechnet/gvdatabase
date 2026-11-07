<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Result files remain useful task attachments; keep them visible after
        // the separate results area is removed.
        DB::table('task_attachments')->where('section', 'results')->update(['section' => 'input']);

        Schema::table('task_attachments', function (Blueprint $table) {
            $table->enum('section', ['input'])->default('input')->change();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('results');
        });
    }

    public function down(): void
    {
        Schema::table('task_attachments', function (Blueprint $table) {
            $table->enum('section', ['input', 'results'])->default('results')->change();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->text('results')->nullable()->after('description');
        });
    }
};
