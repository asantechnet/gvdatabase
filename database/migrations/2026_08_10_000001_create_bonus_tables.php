<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worthy_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->boolean('is_automatic')->default(false);
            $table->decimal('performance_score', 3, 1)->default(0.0);
            $table->string('eligibility_reason');
            $table->text('achievement_details');
            $table->decimal('suggested_amount', 20, 8);
            $table->decimal('selected_amount', 20, 8);
            $table->string('selected_type');
            $table->decimal('granted_amount', 20, 4)->nullable();
            $table->boolean('is_granted')->default(false);
            $table->date('bonus_month');
            $table->string('calculation_key', 80)->nullable()->unique();
            $table->decimal('calculation_score', 12, 4)->nullable();
            $table->json('calculation_inputs')->nullable();
            $table->json('rule_snapshot')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('granted_at')->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['employee_id', 'source_task_id'], 'worthy_employee_task_unique');
        });

        Schema::create('salary_worthy_employee', function (Blueprint $table) {
            $table->foreignId('salary_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worthy_employee_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->decimal('source_amount', 20, 4)->nullable();
            $table->primary(['salary_id', 'worthy_employee_id']);
            $table->unique('worthy_employee_id');
        });

        Schema::create('customer_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->decimal('fee', 15, 2)->nullable();
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->date('deadline')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['customer_id', 'service_id']);
        });

        Schema::create('employee_service_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->decimal('percentage', 5, 2);
            $table->timestamps();

            $table->unique(['employee_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_service_commissions');
        Schema::dropIfExists('customer_service');
        Schema::dropIfExists('salary_worthy_employee');
        Schema::dropIfExists('worthy_employees');
    }
};
