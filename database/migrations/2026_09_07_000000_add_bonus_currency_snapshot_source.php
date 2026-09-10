<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('worthy_employees', 'currency_snapshot_source')) {
            Schema::table('worthy_employees', function (Blueprint $table): void {
                $table->string('currency_snapshot_source', 40)
                    ->default('recorded_at_creation')
                    ->after('base_amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('worthy_employees', 'currency_snapshot_source')) {
            Schema::table('worthy_employees', function (Blueprint $table): void {
                $table->dropColumn('currency_snapshot_source');
            });
        }
    }
};
