<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->decimal('contract_letterhead_safe_x', 5, 2)->default(8.50);
            $table->decimal('contract_letterhead_safe_y', 5, 2)->default(15.00);
            $table->decimal('contract_letterhead_safe_width', 5, 2)->default(83.00);
            $table->decimal('contract_letterhead_safe_height', 5, 2)->default(70.00);
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'contract_letterhead_safe_x',
                'contract_letterhead_safe_y',
                'contract_letterhead_safe_width',
                'contract_letterhead_safe_height',
            ]);
        });
    }
};
