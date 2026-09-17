<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The EducationalBonusSource model soft-deletes through the Auditable
 * concern, but its table was created without the deleted_at column, so
 * every query on bonus sources fails. Adds the missing column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('educational_bonus_sources', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('educational_bonus_sources', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
