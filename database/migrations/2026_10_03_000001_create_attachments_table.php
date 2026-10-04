<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A shared, multi-file attachment store for operational records (expenses,
 * incomes and staff). It mirrors the task-attachment model so every feature can
 * offer the same upload / preview / download / delete experience and accept the
 * same document formats (PDF, images, office files).
 *
 * Existing single-file documents (`expenses.document_*` and
 * `employees.document_*`) are copied in so nothing is lost; their columns are
 * left in place (non-destructive).
 */
return new class extends Migration
{
    private const MORPH_TYPES = [
        'expenses' => 'App\\Models\\Expense',
        'employees' => 'App\\Models\\Employee',
    ];

    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('section', 30)->nullable();
            $table->string('disk', 255)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        $this->backfillDocuments('expenses');
        $this->backfillDocuments('employees');
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }

    private function backfillDocuments(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'document_path')) {
            return;
        }

        $type = self::MORPH_TYPES[$table] ?? null;
        if (! $type) {
            return;
        }

        $hasUploader = Schema::hasColumn($table, 'recorded_by');
        $now = now();

        DB::table($table)
            ->whereNotNull('document_path')
            ->orderBy('id')
            ->get()
            ->each(function ($record) use ($type, $hasUploader, $now): void {
                DB::table('attachments')->insert([
                    'attachable_type' => $type,
                    'attachable_id' => $record->id,
                    'user_id' => $hasUploader ? ($record->recorded_by ?? null) : null,
                    'section' => null,
                    'disk' => 'local',
                    'path' => $record->document_path,
                    'original_name' => $record->document_name ?: basename((string) $record->document_path),
                    'mime_type' => $record->document_mime ?? null,
                    'size' => (int) ($record->document_size ?? 0),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }
};
