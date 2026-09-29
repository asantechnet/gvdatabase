<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRM section: tags, visitors, leads and interactions.
 *
 * `leads.applicant_id` targets the applicants table (created in a later
 * section), so its constraint is added by the completion pass.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('color');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('company');
            $table->string('contact');
            $table->string('email')->nullable();
            $table->string('service');
            $table->string('marketer');
            $table->foreignId('marketer_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->date('date');
            $table->timestamps();
            $table->softDeletes();
            $table->timestamp('converted_at')->nullable();

            $table->index('marketer_id', 'visitors_marketer_id_idx');
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitor_id')->nullable()->constrained()->nullOnDelete();
            // FK added in the completion pass (applicants live in a later section).
            $table->unsignedBigInteger('applicant_id')->nullable()->unique('leads_applicant_id_unique');
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('contact');
            $table->string('email')->nullable();
            $table->string('service_interest')->nullable();
            $table->string('marketer')->nullable();
            $table->foreignId('marketer_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->enum('stage', ['new', 'contacted', 'qualified', 'proposal', 'won', 'lost'])->default('new');
            $table->unsignedTinyInteger('probability')->default(10);
            $table->boolean('probability_is_manual')->default(false);
            $table->decimal('estimated_value', 20, 4)->nullable();
            $table->date('next_follow_up_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['stage', 'next_follow_up_at'], 'leads_stage_next_follow_up_at_index');
            $table->index('marketer_id', 'leads_marketer_id_idx');
        });

        Schema::create('crm_interactions', function (Blueprint $table) {
            $table->id();
            $table->string('contactable_type')->nullable();
            $table->unsignedBigInteger('contactable_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('channel', ['call', 'message', 'email', 'meeting', 'note'])->default('note');
            $table->string('title');
            $table->text('notes');
            $table->timestamp('contacted_at')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('follow_up_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['contactable_type', 'contactable_id']);
            $table->index(['contacted_at', 'follow_up_at'], 'crm_interactions_contacted_at_follow_up_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_interactions');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('visitors');
        Schema::dropIfExists('tags');
    }
};
