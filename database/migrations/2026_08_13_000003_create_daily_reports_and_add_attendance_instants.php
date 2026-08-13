<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->timestamp('checked_in_at')->nullable()->after('check_out_time');
            $table->timestamp('checked_out_at')->nullable()->after('checked_in_at');
            $table->index(['employee_id', 'checked_out_at']);
        });

        Schema::create('daily_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('attendance_id')->unique()->constrained()->restrictOnDelete();
            $table->date('report_date');
            $table->text('summary');
            $table->text('achievements')->nullable();
            $table->text('challenges')->nullable();
            $table->text('next_day_plan')->nullable();
            $table->text('support_needed')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'report_date']);
            $table->index(['report_date', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_reports');

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex(['employee_id', 'checked_out_at']);
            $table->dropColumn(['checked_in_at', 'checked_out_at']);
        });
    }
};
