<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This table already exists from the original customer-specific
        // commission implementation. It is now the authoritative list of
        // delivery employees for a customer service; the legacy employee_id
        // remains as the first employee for backwards-compatible clients.
        DB::table('customer_service')->whereNotNull('employee_id')->orderBy('id')->each(function (object $service): void {
            $percentage = DB::table('employee_service_commissions')
                ->where('employee_id', $service->employee_id)
                ->where('service_id', $service->service_id)
                ->value('percentage');

            if ($percentage !== null) {
                DB::table('employee_customer_service_commissions')->insertOrIgnore([
                    'employee_id' => $service->employee_id,
                    'customer_service_id' => $service->id,
                    'percentage' => $percentage,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        Schema::create('invoice_service_commission_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_service_id')->constrained('invoice_service')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->decimal('percentage', 5, 2);
            $table->timestamps();
            $table->unique(['invoice_service_id', 'employee_id'], 'invoice_service_commission_recipient_unique');
        });

        // Preserve the delivery snapshot on historical invoice lines.
        DB::table('invoice_service')->whereNotNull('employee_id')->orderBy('id')->each(function (object $line): void {
            if ($line->snapshot_commission_percentage !== null) {
                DB::table('invoice_service_commission_recipients')->insertOrIgnore([
                    'invoice_service_id' => $line->id,
                    'employee_id' => $line->employee_id,
                    'percentage' => $line->snapshot_commission_percentage,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        Schema::table('commission_accruals', function (Blueprint $table) {
            $table->dropUnique('commission_accrual_source_role_unique');
            $table->unique(
                ['income_id', 'invoice_service_id', 'employee_id', 'commission_role'],
                'commission_accrual_source_employee_role_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('commission_accruals', function (Blueprint $table) {
            $table->dropUnique('commission_accrual_source_employee_role_unique');
            $table->unique(['income_id', 'invoice_service_id', 'commission_role'], 'commission_accrual_source_role_unique');
        });

        Schema::dropIfExists('invoice_service_commission_recipients');
    }
};
