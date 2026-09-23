<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DESTRUCTIVE step of the services → contracts merge.
 *
 * 1. Remaps educational contract types to `customer` and deletes them, so
 *    only `employment | customer | other` remain.
 * 2. Carries every `customer_service` row into a customer contract + one
 *    `contract_service` line (status map: active→active, paused→terminated,
 *    completed→completed, cancelled→cancelled), copies delivery commissions,
 *    re-points bonus sources and invoice/commission/outsourcing lines.
 * 3. Drops `customer_service`, `employee_customer_service_commissions`,
 *    `contracts.service_id/end_date/duration_months`, `services.duration`,
 *    and the old `customer_service_id` line columns.
 * 4. Deletes the `manage_customer_services` permission + grants.
 *
 * Data cannot be restored by down(); down() only reverses the schema.
 */
return new class extends Migration
{
    private const EDUCATIONAL_SLUGS = ['educational-service', 'scholarship-service', 'test-service', 'visa-service'];

    public function up(): void
    {
        $customerTypeId = DB::table('contract_types')->where('slug', 'customer')->value('id');
        abort_unless($customerTypeId, 500, 'Customer contract type is missing.');

        $educationalIds = DB::table('contract_types')->whereIn('slug', self::EDUCATIONAL_SLUGS)->pluck('id')->all();
        if ($educationalIds !== []) {
            DB::table('contracts')->whereIn('contract_type_id', $educationalIds)->update(['contract_type_id' => $customerTypeId, 'type' => 'Customer']);
            DB::table('contract_templates')->whereIn('contract_type_id', $educationalIds)->update(['contract_type_id' => $customerTypeId]);
            DB::table('contract_policies')->whereIn('type', self::EDUCATIONAL_SLUGS)->update(['type' => 'customer']);
            DB::table('contract_types')->whereIn('id', $educationalIds)->delete();
        }

        foreach (['invoice_service', 'commission_accruals', 'outsourcing_accruals'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->foreignId('contract_service_id')->nullable()->after('customer_service_id')->constrained('contract_service')->nullOnDelete();
            });
        }

        $this->migrateAssignments();

        Schema::table('invoice_service', function (Blueprint $table): void {
            $table->dropForeign(['customer_service_id']);
            $table->dropIndex('invoice_service_customer_service_idx');
            $table->dropColumn('customer_service_id');
        });
        Schema::table('commission_accruals', function (Blueprint $table): void {
            $table->dropForeign(['customer_service_id']);
            $table->dropIndex('commission_accrual_customer_service_idx');
            $table->dropColumn('customer_service_id');
        });
        Schema::table('outsourcing_accruals', function (Blueprint $table): void {
            $table->dropForeign(['customer_service_id']);
            $table->dropColumn('customer_service_id');
        });

        Schema::dropIfExists('employee_customer_service_commissions');
        Schema::dropIfExists('customer_service');

        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('service_id');
            $table->dropColumn(['end_date', 'duration_months']);
        });
        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn('duration');
        });

        $permissionId = DB::table('permissions')->where('name', 'manage_customer_services')->value('id');
        if ($permissionId) {
            DB::table('role_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }

    private function migrateAssignments(): void
    {
        $customerTypeId = DB::table('contract_types')->where('slug', 'customer')->value('id');
        $now = now()->toDateTimeString();
        $lineMap = [];

        DB::table('customer_service')->orderBy('id')->each(function (object $row) use ($customerTypeId, $now, &$lineMap): void {
            $customer = DB::table('customers')->where('id', $row->customer_id)->first();
            $status = match ($row->status) {
                'completed' => 'completed',
                'cancelled' => 'cancelled',
                'paused' => 'terminated',
                default => 'active',
            };
            $start = $row->sold_at ?? substr((string) $row->created_at, 0, 10);

            $contractId = DB::table('contracts')->insertGetId([
                'contract_type_id' => $customerTypeId,
                'customer_id' => $row->customer_id,
                'applicant_id' => $row->applicant_id,
                'sold_by_employee_id' => $row->sold_by_employee_id,
                'marketer_id' => $row->marketer_id,
                'name' => DB::table('services')->where('id', $row->service_id)->value('type'),
                'type' => 'Customer',
                'status' => $status,
                'start_date' => $start,
                'counterparty' => $customer?->name ?? '',
                'counterparty_company' => $customer?->company,
                'counterparty_email' => $customer?->email,
                'counterparty_contact' => $customer?->contact,
                'counterparty_address' => $customer?->address,
                'is_cancelled' => $status === 'cancelled',
                'cancelled_at' => $status === 'cancelled' ? $now : null,
                'content' => '',
                'is_custom' => true,
                'snapshot_data' => json_encode(['entity' => [
                    'customer' => ['id' => $row->customer_id],
                    'applicant_id' => $row->applicant_id,
                    'services' => [['service_id' => $row->service_id, 'fee' => $row->fee, 'currency_id' => $row->currency_id, 'notes' => $row->notes]],
                    'migrated_from' => 'customer_service:'.$row->id,
                ]]),
                'created_at' => $row->created_at ?? $now,
                'updated_at' => $now,
            ]);
            DB::table('contracts')->where('id', $contractId)->update([
                'contract_number' => sprintf('CTR-%s-%06d', substr($start, 0, 4), $contractId),
            ]);

            $lineId = DB::table('contract_service')->insertGetId([
                'contract_id' => $contractId,
                'service_id' => $row->service_id,
                'fee' => $row->fee,
                'currency_id' => $row->currency_id,
                'applicant_id' => $row->applicant_id,
                'customer_package_id' => $row->customer_package_id,
                'sold_by_employee_id' => $row->sold_by_employee_id,
                'marketer_id' => $row->marketer_id,
                'outsourcing_company_id' => $row->outsourcing_company_id,
                'outsourcing_percentage' => $row->outsourcing_percentage,
                'country_id' => $row->country_id,
                'education_level_id' => $row->education_level_id,
                'language_id' => $row->language_id,
                'notes' => $row->notes,
                'created_at' => $row->created_at ?? $now,
                'updated_at' => $now,
            ]);
            $lineMap[$row->id] = $lineId;

            foreach (DB::table('employee_customer_service_commissions')->where('customer_service_id', $row->id)->get() as $commission) {
                DB::table('contract_service_employee')->insert([
                    'contract_service_id' => $lineId,
                    'employee_id' => $commission->employee_id,
                    'percentage' => $commission->percentage,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });

        foreach ($lineMap as $oldId => $newId) {
            DB::table('educational_bonus_sources')
                ->where('source_type', 'App\\Models\\CustomerService')
                ->where('source_id', $oldId)
                ->update(['source_type' => 'App\\Models\\ContractService', 'source_id' => $newId]);
            foreach (['invoice_service', 'commission_accruals', 'outsourcing_accruals'] as $table) {
                DB::table($table)->where('customer_service_id', $oldId)->update(['contract_service_id' => $newId]);
            }
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'manage_customer_services')->value('id');
        if (! $permissionId) {
            $now = now()->toDateTimeString();
            $permissionId = DB::table('permissions')->insertGetId(['name' => 'manage_customer_services', 'category' => 'customer_contracts', 'guard_name' => 'api', 'created_at' => $now, 'updated_at' => $now]);
        }

        Schema::table('services', function (Blueprint $table): void {
            $table->integer('duration')->nullable();
        });
        Schema::table('contracts', function (Blueprint $table): void {
            $table->date('end_date')->nullable();
            $table->unsignedSmallInteger('duration_months')->nullable();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
        });
        foreach (['invoice_service', 'commission_accruals', 'outsourcing_accruals'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->foreignId('customer_service_id')->nullable()->constrained('customer_service')->nullOnDelete();
            });
        }
    }
};
