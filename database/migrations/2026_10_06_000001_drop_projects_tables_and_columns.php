<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the delivery-project concept from the schema.
 *
 * The original squashed migrations no longer create these objects, so this is
 * only meaningful for databases that were migrated before the removal. Every
 * step is guarded so a fresh database (where nothing exists) runs cleanly.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('expenses', 'project_id')) {
            Schema::table('expenses', function (Blueprint $table): void {
                $table->dropForeign(['project_id']);
                $table->dropColumn('project_id');
            });
        }

        if (Schema::hasColumn('boards', 'project_id')) {
            Schema::table('boards', function (Blueprint $table): void {
                $table->dropForeign(['project_id']);
                $table->dropColumn('project_id');
            });
        }

        Schema::dropIfExists('project_service');
        Schema::dropIfExists('projects');
    }

    public function down(): void
    {
        if (! Schema::hasTable('projects')) {
            Schema::create('projects', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('applicant_id')->nullable()->constrained('applicants')->nullOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('status', 30)->default('active');
                $table->date('start_date')->nullable();
                $table->date('due_date')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index('status', 'projects_customer_id_status_index');
                $table->index('applicant_id', 'projects_applicant_id_idx');
            });
        }

        if (! Schema::hasTable('project_service')) {
            Schema::create('project_service', function (Blueprint $table): void {
                $table->foreignId('project_id');
                $table->foreignId('service_id');
                $table->timestamps();

                $table->primary(['project_id', 'service_id']);
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
                $table->foreign('service_id')->references('id')->on('services');
            });
        }

        if (! Schema::hasColumn('boards', 'project_id')) {
            Schema::table('boards', function (Blueprint $table): void {
                $table->foreignId('project_id')->nullable()->after('id')->constrained('projects')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('expenses', 'project_id')) {
            Schema::table('expenses', function (Blueprint $table): void {
                $table->unsignedBigInteger('project_id')->nullable()->index('expense_project_idx');
                $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
            });
        }
    }
};
