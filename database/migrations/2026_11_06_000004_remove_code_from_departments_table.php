<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('departments', 'code')) {
            return;
        }

        Schema::table('departments', function (Blueprint $table) {
            $table->dropUnique('departments_code_unique');
            $table->dropColumn('code');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('departments', 'code')) {
            return;
        }

        Schema::table('departments', function (Blueprint $table) {
            $table->string('code', 40)->nullable()->unique();
        });
    }
};
