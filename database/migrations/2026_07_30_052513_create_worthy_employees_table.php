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
            $table->decimal('performance_score', 3, 1)->default(0.0);
            $table->string('eligibility_reason');
            $table->text('achievement_details');
            $table->decimal('suggested_amount', 10, 2);
            $table->decimal('selected_amount', 10, 2);
            $table->string('selected_type');
            $table->boolean('is_granted')->default(false);
            $table->date('bonus_month');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worthy_employees');
    }
};
