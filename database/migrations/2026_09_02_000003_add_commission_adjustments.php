<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commission_accruals', function (Blueprint $table) {
            $table->unsignedBigInteger('income_id')->nullable()->change();
            $table->unsignedBigInteger('invoice_id')->nullable()->change();
            $table->unsignedBigInteger('invoice_service_id')->nullable()->change();
            $table->unsignedBigInteger('service_id')->nullable()->change();
            $table->string('entry_type')->default('earned')->after('commission_settlement_id');
            $table->text('adjustment_reason')->nullable()->after('commission_base_amount');
            $table->foreignId('recorded_by')->nullable()->after('adjustment_reason')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('commission_accruals', function (Blueprint $table) {
            $table->dropForeign(['recorded_by']);
            $table->dropColumn(['entry_type', 'adjustment_reason', 'recorded_by']);
        });

        // Adjustment rows cannot satisfy the original non-null source keys.
        // They must be removed before restoring the pre-adjustment schema.
        DB::table('commission_accruals')->whereNull('income_id')->delete();
        Schema::table('commission_accruals', function (Blueprint $table) {
            $table->unsignedBigInteger('income_id')->nullable(false)->change();
            $table->unsignedBigInteger('invoice_id')->nullable(false)->change();
            $table->unsignedBigInteger('invoice_service_id')->nullable(false)->change();
            $table->unsignedBigInteger('service_id')->nullable(false)->change();
        });
    }
};
