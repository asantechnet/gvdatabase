<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('automatic_attendance_enabled')->default(false)->change();
        });

        $this->restoreMissingProjectServiceScope();
        $this->enforceOnePayrollPerEmployeeMonth();
        $this->moveTaskAttachmentsToPrivateStorage();
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('automatic_attendance_enabled')->default(true)->change();
        });

        if ($this->hasIndex('salaries', 'salaries_employee_month_unique')) {
            Schema::table('salaries', function (Blueprint $table) {
                $table->dropUnique('salaries_employee_month_unique');
            });
        }
    }

    private function restoreMissingProjectServiceScope(): void
    {
        if (! Schema::hasTable('project_service')) {
            Schema::create('project_service', function (Blueprint $table) {
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
                $table->foreignId('service_id')->constrained()->restrictOnDelete();
                $table->timestamps();
                $table->primary(['project_id', 'service_id']);
            });
        }

        DB::table('projects')->whereNotNull('customer_id')->orderBy('id')->each(function (object $project): void {
            if (DB::table('project_service')->where('project_id', $project->id)->exists()) {
                return;
            }

            $now = now();
            $rows = DB::table('customer_service')
                ->where('customer_id', $project->customer_id)
                ->distinct()->pluck('service_id')
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

    private function enforceOnePayrollPerEmployeeMonth(): void
    {
        $duplicates = DB::table('salaries')
            ->select('employee_id', 'salary_month', DB::raw('COUNT(*) AS aggregate'))
            ->groupBy('employee_id', 'salary_month')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            $keys = $duplicates->map(fn (object $row): string => "{$row->employee_id}:{$row->salary_month}")->implode(', ');
            throw new RuntimeException("Duplicate payroll periods require reconciliation before migration: {$keys}");
        }

        if (! $this->hasIndex('salaries', 'salaries_employee_month_unique')) {
            Schema::table('salaries', function (Blueprint $table) {
                $table->unique(['employee_id', 'salary_month'], 'salaries_employee_month_unique');
            });
        }
    }

    private function moveTaskAttachmentsToPrivateStorage(): void
    {
        DB::table('task_attachments')->where('disk', 'public')->orderBy('id')->each(function (object $attachment): void {
            $public = Storage::disk('public');
            $private = Storage::disk('local');

            if (! $public->exists($attachment->path)) {
                throw new RuntimeException("Task attachment {$attachment->id} is missing from public storage.");
            }

            $stream = $public->readStream($attachment->path);
            if (! is_resource($stream) || ! $private->writeStream($attachment->path, $stream)) {
                if (is_resource($stream)) {
                    fclose($stream);
                }
                throw new RuntimeException("Task attachment {$attachment->id} could not be moved to private storage.");
            }
            fclose($stream);

            DB::table('task_attachments')->where('id', $attachment->id)->update(['disk' => 'local']);
            $public->delete($attachment->path);
        });
    }

    private function hasIndex(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))->contains(fn (array $index): bool => $index['name'] === $name);
    }
};
