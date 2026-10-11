<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        $this->rename('contract_service', 'applicant_contract', 'App\\Models\\ContractService', 'App\\Models\\ApplicantContract');
    }

    public function down(): void
    {
        $this->rename('applicant_contract', 'contract_service', 'App\\Models\\ApplicantContract', 'App\\Models\\ContractService');
    }

    private function rename(string $from, string $to, string $oldClass, string $newClass): void
    {
        Schema::withoutForeignKeyConstraints(function () use ($from, $to) {
            Schema::rename($from, $to);
            if (Schema::hasTable($from.'_employee')) {
                Schema::rename($from.'_employee', $to.'_employee');
            }
            foreach ([$to.'_employee', 'invoice_service', 'commission_accruals', 'outsourcing_accruals'] as $table) {
                // The commission/outsourcing ledgers were removed later, so a
                // rollback on a current schema must skip tables that are gone.
                if (Schema::hasTable($table)) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->renameColumn($from.'_id', $to.'_id'));
                }
            }
        });

        DB::transaction(function () use ($from, $to, $oldClass, $newClass) {
            $oldSetting = DB::table('audit_resource_settings')->where('resource_key', $from.'s')->first();
            if ($oldSetting) {
                $target = DB::table('audit_resource_settings')->where('resource_key', $to.'s')->first();
                if ($target) {
                    DB::table('audit_resource_settings')->where('id', $target->id)->update([
                        'enabled' => $oldSetting->enabled, 'updated_by' => $oldSetting->updated_by,
                        'updated_at' => $oldSetting->updated_at,
                    ]);
                    DB::table('audit_resource_settings')->where('id', $oldSetting->id)->delete();
                } else {
                    DB::table('audit_resource_settings')->where('id', $oldSetting->id)->update(['resource_key' => $to.'s']);
                }
            }

            foreach ([['audit_logs', 'auditable_type'], ['attachments', 'attachable_type'], ['educational_bonus_sources', 'source_type']] as [$table, $column]) {
                DB::table($table)->where($column, $oldClass)->update([$column => $newClass]);
            }
            DB::table('audit_logs')->where('table_name', $from)->update(['table_name' => $to]);
            DB::table('audit_logs')->where('subject_type', $from.'s')->update(['subject_type' => $to.'s']);

            foreach (['audit_logs' => ['old_values', 'new_values', 'changed_columns'], 'notifications' => ['data']] as $table => $columns) {
                DB::table($table)->select(['id', ...$columns])->orderBy('id')->chunkById(500, function ($rows) use ($table, $columns, $from, $to, $oldClass, $newClass) {
                    foreach ($rows as $row) {
                        $updates = [];
                        foreach ($columns as $column) {
                            $payload = json_decode($row->$column ?? '', true);
                            if (! is_array($payload)) {
                                continue;
                            }
                            $renamed = $column === 'changed_columns'
                                ? array_map(fn ($key) => $key === $from.'_id' ? $to.'_id' : $key, $payload)
                                : $this->renamePayload($payload, $from, $to, $oldClass, $newClass);
                            if ($renamed !== $payload) {
                                $updates[$column] = json_encode($renamed, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                            }
                        }
                        if ($updates !== []) {
                            DB::table($table)->where('id', $row->id)->update($updates);
                        }
                    }
                });
            }
        });
    }

    private function renamePayload(array $payload, string $from, string $to, string $oldClass, string $newClass): array
    {
        $renamed = [];
        foreach ($payload as $key => $value) {
            $newKey = match ($key) {
                $from.'_id' => $to.'_id',
                $from.'s' => $to.'s',
                default => $key,
            };
            if (is_array($value)) {
                $value = $this->renamePayload($value, $from, $to, $oldClass, $newClass);
            } elseif ($key === 'resource' && $value === $from.'s') {
                $value = $to.'s';
            } elseif (in_array($key, ['source_type', 'attachable_type', 'auditable_type'], true) && $value === $oldClass) {
                $value = $newClass;
            }
            $renamed[$newKey] = $value;
        }

        return $renamed;
    }
};
