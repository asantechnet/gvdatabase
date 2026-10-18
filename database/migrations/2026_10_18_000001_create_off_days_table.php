<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Company-wide off days (public holidays).
 *
 * HR announces a contiguous date range with a reason. A row only affects
 * attendance once it is approved by a holder of the dedicated
 * `approve_off_days` permission, so announcing and approving can be separated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('off_days', function (Blueprint $table) {
            $table->id();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('reason', 1000);
            $table->string('status', 20)->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['start_date', 'end_date'], 'off_days_range_index');
            $table->index(['status', 'start_date'], 'off_days_status_start_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('off_days');
    }
};
