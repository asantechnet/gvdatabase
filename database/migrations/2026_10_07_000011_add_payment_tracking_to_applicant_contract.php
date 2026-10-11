<?php

use App\Support\BuiltInService;
use App\Support\PaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::table('applicant_contract', function (Blueprint $table) {
            $table->enum('service', BuiltInService::ALL)->nullable()->after('contract_id');
            $table->index(['applicant_id', 'service'], 'applicant_contract_applicant_service_idx');

            $table->decimal('discount', 20, 6)->default(0)->after('fee');
            $table->decimal('currency_rate', 15, 8)->nullable()->after('currency_id');
            $table->decimal('amount_paid', 20, 6)->default(0);
            $table->enum('payment_status', PaymentStatus::ALL)->default(PaymentStatus::UNPAID);
            $table->boolean('payment_status_overridden')->default(false);
            $table->boolean('amount_paid_overridden')->default(false);
        });

    }

    public function down(): void
    {
        Schema::withoutForeignKeyConstraints(function () {
            Schema::table('applicant_contract', function (Blueprint $table) {
                $table->dropIndex('applicant_contract_applicant_service_idx');
                $table->dropColumn([
                    'service',
                    'discount',
                    'currency_rate',
                    'amount_paid',
                    'payment_status',
                    'payment_status_overridden',
                    'amount_paid_overridden',
                ]);
            });
        });
    }
};
