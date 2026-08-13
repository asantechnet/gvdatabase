<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_deactivation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('pending_user_id')->nullable()->unique()->constrained('users')->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('requester_rank');
            $table->json('requester_roles');
            $table->timestamp('requested_at');
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('reviewer_rank')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('audit_metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'requested_at']);
            $table->index(['user_id', 'requested_at']);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('event', 64)->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_deactivation_requests');

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('event', 20)->change();
        });
    }
};
