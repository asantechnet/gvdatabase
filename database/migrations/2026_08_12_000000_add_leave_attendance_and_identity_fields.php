<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('paid_off_days_per_year')->default(0)->after('footer_clr');
            $table->string('business_licence')->nullable()->after('original_company_name');
            $table->time('office_open_time')->nullable()->after('business_licence');
            $table->time('office_close_time')->nullable()->after('office_open_time');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->string('id_number', 100)->nullable()->unique()->after('contact');
            $table->string('guarantor_id_number', 100)->nullable()->after('id_number');
            $table->text('guarantor_address')->nullable()->after('guarantor_id_number');
            $table->string('guarantor_licence', 255)->nullable()->after('guarantor_address');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->time('check_in_time')->nullable()->after('status');
            $table->time('check_out_time')->nullable()->after('check_in_time');
        });

        Schema::create('paid_time_offs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedSmallInteger('year');
            $table->string('reason', 1000);
            $table->timestamps();

            $table->unique(['employee_id', 'date']);
            $table->index(['employee_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paid_time_offs');

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['check_in_time', 'check_out_time']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique(['id_number']);
            $table->dropColumn(['id_number', 'guarantor_id_number', 'guarantor_address', 'guarantor_licence']);
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['paid_off_days_per_year', 'business_licence', 'office_open_time', 'office_close_time']);
        });
    }
};
