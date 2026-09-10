<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Restore the explicit delivery scope that was removed when projects were
     * temporarily modelled as inheriting every service from their customer.
     */
    public function up(): void
    {
        if (! Schema::hasTable('project_service')) {
            Schema::create('project_service', function (Blueprint $table) {
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
                $table->foreignId('service_id')->constrained()->restrictOnDelete();
                $table->timestamps();

                $table->primary(['project_id', 'service_id']);
            });
        }

        // Older deployments may already have executed the destructive domain
        // migration. Exact legacy scope is unrecoverable without a backup, so
        // restore the conservative customer-service scope only for projects
        // that currently have no explicit assignments.
        DB::table('projects')->whereNotNull('customer_id')->orderBy('id')->each(function (object $project): void {
            if (DB::table('project_service')->where('project_id', $project->id)->exists()) {
                return;
            }

            $now = now();
            $rows = DB::table('customer_service')
                ->where('customer_id', $project->customer_id)
                ->distinct()
                ->pluck('service_id')
                ->map(fn (int $serviceId): array => [
                    'project_id' => $project->id,
                    'service_id' => $serviceId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

            if ($rows !== []) {
                DB::table('project_service')->insertOrIgnore($rows);
            }
        });
    }

    public function down(): void
    {
        // This migration may have adopted a pre-existing table. Dropping it
        // would destroy assignments, so rollback intentionally preserves it.
    }
};
