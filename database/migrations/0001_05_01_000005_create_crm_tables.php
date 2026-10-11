<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRM section: tags, visitors and interactions. A visitor converts straight to
 * an applicant; there is no lead entity.
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
        Schema::dropIfExists('visitors');
        Schema::dropIfExists('tags');
    }
};
