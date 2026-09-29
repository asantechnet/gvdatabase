<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Variants are intentionally one level deep. Existing child variants
        // stay valid catalogue entries; they simply become direct variants.
        if (Schema::hasColumn('service_variants', 'parent_id')) {
            DB::table('service_variants')->update(['parent_id' => null]);
            Schema::table('service_variants', fn (Blueprint $table) => $table->dropConstrainedForeignId('parent_id'));
        }
    }

    public function down(): void
    {
        Schema::table('service_variants', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('service_id')->constrained('service_variants')->nullOnDelete();
            $table->index(['service_id', 'parent_id']);
        });
    }
};
