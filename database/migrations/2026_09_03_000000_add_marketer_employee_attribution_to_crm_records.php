<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<int, string> */
    private array $crmTables = ['customers', 'leads', 'visitors'];

    public function up(): void
    {
        foreach ($this->crmTables as $crmTable) {
            Schema::table($crmTable, function (Blueprint $table) use ($crmTable): void {
                $table->foreignId('marketer_id')
                    ->nullable()
                    ->after('marketer')
                    ->constrained('employees')
                    ->nullOnDelete();
                $table->index('marketer_id', "{$crmTable}_marketer_id_idx");
            });
        }

        // Preserve legacy CRM attribution where a historic marketer directory
        // entry has already been linked to an employee.
        $employeeIdsByLegacyName = DB::table('marketers')
            ->whereNull('deleted_at')
            ->whereNotNull('employee_id')
            ->pluck('employee_id', 'name');

        foreach ($this->crmTables as $crmTable) {
            DB::table($crmTable)
                ->whereNull('marketer_id')
                ->whereNotNull('marketer')
                ->orderBy('id')
                ->each(function (object $record) use ($crmTable, $employeeIdsByLegacyName): void {
                    $employeeId = $employeeIdsByLegacyName[$record->marketer] ?? null;

                    if ($employeeId) {
                        DB::table($crmTable)->where('id', $record->id)->update(['marketer_id' => $employeeId]);
                    }
                });
        }
    }

    public function down(): void
    {
        foreach ($this->crmTables as $crmTable) {
            Schema::table($crmTable, function (Blueprint $table) use ($crmTable): void {
                $table->dropIndex("{$crmTable}_marketer_id_idx");
                $table->dropConstrainedForeignId('marketer_id');
            });
        }
    }
};
