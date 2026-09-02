<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stocks') && ! Schema::hasTable('assets')) {
            Schema::rename('stocks', 'assets');
        }

        if (Schema::hasTable('stock_allocations') && ! Schema::hasTable('asset_allocations')) {
            Schema::rename('stock_allocations', 'asset_allocations');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('asset_allocations') && ! Schema::hasTable('stock_allocations')) {
            Schema::rename('asset_allocations', 'stock_allocations');
        }

        if (Schema::hasTable('assets') && ! Schema::hasTable('stocks')) {
            Schema::rename('assets', 'stocks');
        }
    }
};
