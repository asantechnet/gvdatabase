<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Case outcomes and educational bonuses. */
return new class extends Migration
{
    public function up(): void
    {

        Schema::create('case_wins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('applicants')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('applicant_stage_history_id')->nullable()->constrained('applicant_stage_histories')->nullOnDelete();
            $table->timestamp('won_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('stage_key', 100);
            $table->timestamps();
            $table->softDeletes();

            $table->unique('applicant_id', 'case_wins_applicant_id_unique');
            $table->unique('applicant_stage_history_id', 'case_wins_applicant_stage_history_id_unique');
            $table->index(['employee_id', 'won_at'], 'case_wins_employee_id_won_at_index');
        });

        Schema::create('educational_bonus_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('applicant_id')->nullable()->constrained('applicants')->nullOnDelete();
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->decimal('source_amount', 20, 6);
            $table->decimal('bonus_amount', 20, 6)->default(0);
            $table->string('status')->default('pending');
            $table->string('rule_source')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['source_type', 'source_id', 'employee_id'], 'educational_bonus_source_unique');
            $table->index(['source_type', 'source_id'], 'educational_bonus_sources_source_type_source_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('educational_bonus_sources');
        Schema::dropIfExists('case_wins');
    }
};
