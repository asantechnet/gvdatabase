<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->enum('status', ['present', 'absent', 'paid-off-day', 'unpaid-off-day'])->change();
        });
    }

    public function down(): void
    {
        // Unpaid leave is included in absence totals throughout the application,
        // so absence is the closest legacy status when rolling back.
        DB::table('attendances')->where('status', 'unpaid-off-day')->update(['status' => 'absent']);

        Schema::table('attendances', function (Blueprint $table) {
            $table->enum('status', ['present', 'absent', 'paid-off-day'])->change();
        });
    }
};
