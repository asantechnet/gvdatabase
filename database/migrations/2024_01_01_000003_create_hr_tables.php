<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('father_name');
            $table->string('contact');
            $table->string('id_number', 100)->nullable()->unique();
            $table->string('guarantor_id_number', 100)->nullable();
            $table->string('guarantor_phone', 30)->nullable();
            $table->text('guarantor_address')->nullable();
            $table->string('guarantor_licence', 255)->nullable();
            $table->string('education_lvl');
            $table->foreignId('position_id')->constrained()->cascadeOnDelete();
            $table->string('employment_type')->default('salaried');
            $table->foreignId('office_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('salary', 10, 2)->nullable();
            $table->decimal('total_worked_hours', 14, 2)->default(0);
            $table->text('addr');
            $table->string('ID_file')->nullable();
            $table->string('assure_file')->nullable();
            $table->string('avatar')->nullable();
            $table->string('department')->nullable();
            $table->date('join_date');
            $table->time('work_start_time')->nullable();
            $table->time('work_end_time')->nullable();
            $table->string('contract_duration');
            $table->string('tax_number')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('position_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('position_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('assigned_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->timestamps();

            $table->unique(['position_id', 'user_id']);
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->foreignId('office_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->enum('status', ['present', 'absent', 'paid-off-day']);
            $table->time('logged_at');
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->decimal('check_in_latitude', 10, 7)->nullable();
            $table->decimal('check_in_longitude', 10, 7)->nullable();
            $table->unsignedInteger('check_in_accuracy_meters')->nullable();
            $table->unsignedInteger('check_in_distance_meters')->nullable();
            $table->string('check_in_source', 30)->nullable();
            $table->decimal('check_out_latitude', 10, 7)->nullable();
            $table->decimal('check_out_longitude', 10, 7)->nullable();
            $table->unsignedInteger('check_out_accuracy_meters')->nullable();
            $table->unsignedInteger('check_out_distance_meters')->nullable();
            $table->string('check_out_source', 30)->nullable();
            $table->timestamp('geofence_exit_detected_at')->nullable();
            $table->unsignedSmallInteger('break_minutes')->nullable();
            $table->unsignedInteger('gross_minutes')->nullable();
            $table->unsignedInteger('net_worked_minutes')->nullable();
            $table->unsignedInteger('required_minutes')->nullable();
            $table->unsignedInteger('late_arrival_minutes')->default(0);
            $table->unsignedInteger('early_departure_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->string('missing_punch_state', 20)->default('none');
            $table->boolean('is_overnight_shift')->default(false);
            $table->string('calculation_hash', 64)->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['employee_id', 'date']);
            $table->index(['employee_id', 'checked_out_at']);
            $table->index(['date', 'office_id', 'status']);
            $table->index(['calculated_at', 'calculation_hash']);
        });

        Schema::create('paid_time_offs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedSmallInteger('year');
            $table->string('reason', 1000);
            $table->boolean('is_granted')->default(false);
            $table->boolean('is_paid')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('granted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['employee_id', 'date']);
            $table->index(['employee_id', 'year']);
        });

        Schema::create('salaries', function (Blueprint $table) {
            $table->id();
            $table->string('payslip_number')->nullable()->unique();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->string('salary_month', 7);
            $table->decimal('amount_paid', 20, 8);
            $table->integer('worked_days')->default(30);
            $table->integer('total_days')->default(30);
            $table->unsignedSmallInteger('paid_off_days')->default(0);
            $table->unsignedSmallInteger('absent_days')->default(0);
            $table->unsignedSmallInteger('unpaid_off_days')->default(0);
            $table->decimal('bonus', 20, 8)->default(0);
            $table->decimal('total_amount', 20, 8);
            $table->string('remarks', 255)->nullable();
            $table->date('payment_date');
            $table->string('currency_code', 10)->nullable();
            $table->string('currency_name')->nullable();
            $table->string('currency_symbol', 20)->nullable();
            $table->unsignedTinyInteger('currency_decimal_places')->nullable();
            $table->string('currency_snapshot_source', 40)->default('recorded_at_creation');
            $table->text('currency_snapshot_note')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->string('cnv_curr_shrt', 10);
            $table->string('base_curr_shrt', 10);
            $table->decimal('rate', 20, 8);
            $table->decimal('base_amount', 20, 4);
        });

        Schema::create('salary_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_id')->constrained()->cascadeOnDelete();
            $table->string('description', 255);
            $table->decimal('amount', 20, 8);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('serial_number');
            $table->string('item');
            $table->integer('quantity');
            $table->decimal('price', 14, 2)->default(0);
            $table->string('model');
            $table->string('condition')->default('good');
            $table->foreignId('staff_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('office_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
        Schema::dropIfExists('salary_deductions');
        Schema::dropIfExists('salaries');
        Schema::dropIfExists('paid_time_offs');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('position_user');
        Schema::dropIfExists('employees');
    }
};
