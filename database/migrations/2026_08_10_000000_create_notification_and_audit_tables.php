<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50)->default('info');
            $table->string('notification_key', 100)->nullable();
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('date_time')->useCurrent();
            // Optional business context. Delivery and recipient state live in notification_user.
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('go_to_url', 2048)->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'date_time']);
            $table->index(['notification_key', 'date_time']);
        });

        Schema::create('notification_user', function (Blueprint $table) {
            $table->foreignId('notification_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_read')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->unique(['notification_id', 'user_id']);
            $table->index(['user_id', 'deleted_at', 'is_read']);
        });

        Schema::create('recurring_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('event_type', 20);
            $table->string('status', 20)->default('active');
            $table->string('frequency', 20);
            $table->unsignedSmallInteger('interval')->default(1);
            $table->date('start_date');
            $table->date('next_occurrence_date');
            $table->date('last_occurrence_date')->nullable();
            $table->date('end_date')->nullable();
            $table->json('payload');
            $table->timestamps();
            $table->index(['status', 'next_occurrence_date']);
        });

        Schema::create('recurring_event_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_event_id')->constrained()->cascadeOnDelete();
            $table->date('occurrence_date');
            $table->string('status', 20)->default('pending');
            $table->foreignId('notification_id')->nullable()->constrained('notifications')->nullOnDelete();
            $table->nullableMorphs('result');
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->timestamps();
            $table->unique(['recurring_event_id', 'occurrence_date'], 'rec_event_occurrence_date_unique');
            $table->index(['status', 'occurrence_date']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event', 64);
            $table->string('table_name');
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('review_status', 20)->default('pending');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('changed_columns')->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('method', 10)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['table_name', 'event', 'created_at']);
            $table->index(['review_status', 'created_at']);
            $table->index(['employee_id', 'created_at']);
        });

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
    }

    public function down(): void
    {
        Schema::dropIfExists('account_deactivation_requests');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('recurring_event_occurrences');
        Schema::dropIfExists('recurring_events');
        Schema::dropIfExists('notification_user');
        Schema::dropIfExists('notifications');
    }
};
