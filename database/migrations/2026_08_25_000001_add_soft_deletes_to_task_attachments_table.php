<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('task_attachments', 'deleted_at')) {
            return;
        }

        Schema::table('task_attachments', function (Blueprint $table) {
            $table->softDeletes()->after('updated_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('task_attachments', 'deleted_at')) {
            return;
        }

        Schema::table('task_attachments', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
