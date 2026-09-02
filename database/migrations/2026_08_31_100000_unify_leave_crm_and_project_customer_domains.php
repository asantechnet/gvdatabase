<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A leave is a quantity, not necessarily a full calendar day. Keep the
        // date unique per employee and record the approved paid/unpaid split.
        Schema::rename('paid_time_offs', 'leaves');
        Schema::table('leaves', function (Blueprint $table) {
            $table->decimal('duration_days', 3, 1)->default(1)->after('year');
            $table->decimal('paid_days', 3, 1)->nullable()->after('is_paid');
            $table->boolean('paid_override')->default(false)->after('paid_days');
            $table->text('approval_note')->nullable()->after('reason');
        });
        DB::table('leaves')->where('is_granted', true)->update([
            'paid_days' => DB::raw('CASE WHEN is_paid = 1 THEN duration_days ELSE 0 END'),
        ]);

        Schema::table('settings', fn (Blueprint $table) =>
            $table->renameColumn('paid_off_days_per_year', 'paid_leave_days_per_year'));
        Schema::table('salaries', function (Blueprint $table) {
            $table->renameColumn('paid_off_days', 'paid_leave_days');
            $table->renameColumn('unpaid_off_days', 'unpaid_leave_days');
        });
        Schema::table('attendances', fn (Blueprint $table) =>
            $table->enum('status', ['present', 'absent', 'paid-leave', 'unpaid-leave', 'paid-off-day', 'unpaid-off-day'])->change());
        DB::table('attendances')->where('status', 'paid-off-day')->update(['status' => 'paid-leave']);
        DB::table('attendances')->where('status', 'unpaid-off-day')->update(['status' => 'unpaid-leave']);
        Schema::table('attendances', fn (Blueprint $table) =>
            $table->enum('status', ['present', 'absent', 'paid-leave', 'unpaid-leave'])->change());

        Schema::table('visitors', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('marketer');
            $table->timestamp('converted_at')->nullable();
        });
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('contact');
            $table->string('email')->nullable();
            $table->string('service_interest')->nullable();
            $table->string('marketer')->nullable();
            $table->enum('stage', ['new', 'contacted', 'qualified', 'proposal', 'won', 'lost'])->default('new');
            $table->unsignedTinyInteger('probability')->default(10);
            $table->decimal('estimated_value', 20, 4)->nullable();
            $table->date('next_follow_up_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['stage', 'next_follow_up_at']);
        });
        Schema::create('crm_interactions', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('contactable');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('channel', ['call', 'message', 'email', 'meeting', 'note'])->default('note');
            $table->string('title');
            $table->text('notes');
            $table->timestamp('contacted_at');
            $table->timestamp('follow_up_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['contacted_at', 'follow_up_at']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index(['customer_id', 'status']);
        });
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('board_id')->constrained()->nullOnDelete();
            $table->index('customer_id');
        });

        // Preserve existing project ownership when every linked service points
        // to exactly one customer; ambiguous legacy projects stay unassigned.
        DB::table('projects')->orderBy('id')->each(function (object $project): void {
            $customerIds = DB::table('project_service')
                ->join('customer_service', 'customer_service.service_id', '=', 'project_service.service_id')
                ->where('project_service.project_id', $project->id)
                ->distinct()->pluck('customer_service.customer_id');
            if ($customerIds->count() === 1) {
                DB::table('projects')->where('id', $project->id)->update(['customer_id' => $customerIds->first()]);
            }
        });
        Schema::dropIfExists('task_service');
        Schema::dropIfExists('project_service');
    }

    public function down(): void
    {
        Schema::create('project_service', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->primary(['project_id', 'service_id']);
        });
        Schema::create('task_service', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->primary(['task_id', 'service_id']);
        });
        Schema::table('attendances', fn (Blueprint $table) =>
            $table->enum('status', ['present', 'absent', 'paid-leave', 'unpaid-leave', 'paid-off-day', 'unpaid-off-day'])->change());
        DB::table('attendances')->where('status', 'paid-leave')->update(['status' => 'paid-off-day']);
        DB::table('attendances')->where('status', 'unpaid-leave')->update(['status' => 'unpaid-off-day']);
        Schema::table('attendances', fn (Blueprint $table) =>
            $table->enum('status', ['present', 'absent', 'paid-off-day', 'unpaid-off-day'])->change());
        Schema::table('tasks', fn (Blueprint $table) => $table->dropConstrainedForeignId('customer_id'));
        Schema::table('projects', fn (Blueprint $table) => $table->dropConstrainedForeignId('customer_id'));
        Schema::dropIfExists('crm_interactions');
        Schema::dropIfExists('leads');
        Schema::table('visitors', fn (Blueprint $table) => $table->dropColumn(['notes', 'converted_at']));
        Schema::table('salaries', function (Blueprint $table) {
            $table->renameColumn('paid_leave_days', 'paid_off_days');
            $table->renameColumn('unpaid_leave_days', 'unpaid_off_days');
        });
        Schema::table('settings', fn (Blueprint $table) =>
            $table->renameColumn('paid_leave_days_per_year', 'paid_off_days_per_year'));
        Schema::table('leaves', fn (Blueprint $table) =>
            $table->dropColumn(['duration_days', 'paid_days', 'paid_override', 'approval_note']));
        Schema::rename('leaves', 'paid_time_offs');
    }
};
