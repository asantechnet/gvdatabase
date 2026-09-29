<?php

use App\Support\AuditRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Notification and audit section: notifications with per-user read state,
 * recurring events with occurrences, the audit log, per-resource audit
 * switches (seeded from the registry, enabled) and account deactivation
 * requests.
 */
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
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('go_to_url', 2048)->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'date_time'], 'notifications_employee_id_date_time_index');
            $table->index(['notification_key', 'date_time'], 'notifications_notification_key_date_time_index');
        });

        // Composite-key pivot without an id column, matching the final state.
        Schema::create('notification_user', function (Blueprint $table) {
            $table->foreignId('notification_id');
            $table->foreignId('user_id');
            $table->boolean('is_read')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['notification_id', 'user_id'], 'notification_user_notification_id_user_id_unique');
            $table->index(['user_id', 'deleted_at', 'is_read'], 'notification_user_user_id_deleted_at_is_read_index');
            $table->foreign('notification_id')->references('id')->on('notifications')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('recurring_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users');
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

            $table->index(['status', 'next_occurrence_date'], 'recurring_events_status_next_occurrence_date_index');
        });

        Schema::create('recurring_event_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_event_id')->constrained('recurring_events')->cascadeOnDelete();
            $table->date('occurrence_date');
            $table->string('status', 20)->default('pending');
            $table->foreignId('notification_id')->nullable()->constrained('notifications')->nullOnDelete();
            $table->string('result_type')->nullable();
            $table->unsignedBigInteger('result_id')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->timestamps();

            $table->unique(['recurring_event_id', 'occurrence_date'], 'rec_event_occurrence_date_unique');
            $table->index(['result_type', 'result_id'], 'recurring_event_occurrences_result_type_result_id_index');
            $table->index(['status', 'occurrence_date'], 'recurring_event_occurrences_status_occurrence_date_index');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event', 64);
            $table->string('table_name');
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->string('subject_type', 80)->nullable();
            $table->string('subject_label')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('audited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('review_status', 20)->default('pending');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('audited_at')->nullable();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('changed_columns')->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('method', 10)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['auditable_type', 'auditable_id'], 'audit_logs_auditable_type_auditable_id_index');
            $table->index(['table_name', 'event', 'created_at'], 'audit_logs_table_name_event_created_at_index');
            $table->index(['review_status', 'created_at'], 'audit_logs_review_status_created_at_index');
            $table->index(['employee_id', 'created_at'], 'audit_logs_employee_id_created_at_index');
            $table->index(['audited_at', 'created_at'], 'audit_logs_audited_at_created_at_index');
            $table->index(['audited_by', 'audited_at'], 'audit_logs_audited_by_audited_at_index');
            $table->index(['subject_type', 'created_at'], 'audit_logs_subject_type_created_at_index');
        });

        Schema::create('audit_resource_settings', function (Blueprint $table) {
            $table->id();
            $table->string('resource_key', 80)->unique();
            $table->boolean('enabled')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['enabled', 'resource_key'], 'audit_resource_settings_enabled_resource_key_index');
        });

        // The final state ships every registry resource with auditing enabled.
        $now = now();
        foreach (array_keys(AuditRegistry::resources()) as $resourceKey) {
            DB::table('audit_resource_settings')->insertOrIgnore([
                'resource_key' => $resourceKey,
                'enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::create('account_deactivation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('pending_user_id')->nullable()->unique()->constrained('users');
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('requester_rank');
            $table->json('requester_roles');
            $table->timestamp('requested_at')->useCurrent()->useCurrentOnUpdate();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('reviewer_rank')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('audit_metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'requested_at'], 'account_deactivation_requests_status_requested_at_index');
            $table->index(['user_id', 'requested_at'], 'account_deactivation_requests_user_id_requested_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_deactivation_requests');
        Schema::dropIfExists('audit_resource_settings');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('recurring_event_occurrences');
        Schema::dropIfExists('recurring_events');
        Schema::dropIfExists('notification_user');
        Schema::dropIfExists('notifications');
    }
};
