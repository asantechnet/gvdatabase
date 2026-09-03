<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_service', function (Blueprint $table) {
            // The delivery owner and acquisition owner are deliberately
            // separate: a marketer may win a service that another employee
            // later performs.
            $table->foreignId('employee_id')->nullable()->after('service_id')
                ->constrained('employees')->nullOnDelete();
            $table->foreignId('marketer_id')->nullable()->after('employee_id')
                ->constrained('employees')->nullOnDelete();
        });

        // Preserve attribution for existing service agreements once customer
        // marketer ownership has been migrated to employees.
        DB::table('customer_service')->orderBy('id')->each(function (object $customerService): void {
            $marketerId = DB::table('customers')
                ->where('id', $customerService->customer_id)
                ->value('marketer_id');

            if ($marketerId) {
                DB::table('customer_service')
                    ->where('id', $customerService->id)
                    ->whereNull('marketer_id')
                    ->update(['marketer_id' => $marketerId]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_service', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_id');
            $table->dropConstrainedForeignId('marketer_id');
        });
    }
};
