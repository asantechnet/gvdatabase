<?php

use App\Support\BuiltInService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::withoutForeignKeyConstraints(function (): void {
            if (Schema::hasColumn('contract_document_designs', 'service_id')) {
                DB::table('contract_document_designs')->whereNotNull('service_id')->delete();
            }
            foreach ([
                'contracts' => ['source_service_id'],
                'contract_templates' => ['service_id', 'package_id'],
                'applicant_contract' => ['service_id', 'service_variant_id', 'customer_package_id', 'country_id', 'education_level_id', 'language_id'],
                'contract_document_designs' => ['service_id'],
            ] as $table => $columns) {
                $this->dropColumns($table, $columns);
            }
            if (! Schema::hasColumn('contract_document_designs', 'service')) {
                Schema::table('contract_document_designs', function (Blueprint $table): void {
                    $table->enum('service', BuiltInService::ALL)->nullable();
                    $table->unique(['contract_type_id', 'service'], 'contract_design_type_service_unique');
                });
                // Catalogue-scoped artwork has no subject now. Keep one type-wide design.
                $seen = [];
                DB::table('contract_document_designs')->orderBy('id')->get()->each(function ($row) use (&$seen): void {
                    if (isset($seen[$row->contract_type_id])) {
                        DB::table('contract_document_designs')->where('id', $row->id)->delete();
                    } else {
                        $seen[$row->contract_type_id] = true;
                    }
                });
            }
            foreach (['task_service', 'contract_template_services', 'package_service', 'customer_packages', 'scholarships', 'test_offers', 'service_variants', 'packages', 'package_types', 'services'] as $table) {
                Schema::dropIfExists($table);
            }
        });

        DB::table('permissions')->whereIn('name', [
            'view_services', 'manage_services', 'view_packages', 'manage_packages', 'issue_packages',
            'view_package_type', 'create_package_type', 'edit_package_type', 'delete_package_type',
            'view_educational_services', 'manage_educational_services', 'view_applicant_services',
            'view_leads', 'create_leads', 'edit_leads', 'delete_leads', 'convert_leads',
        ])->delete();
        $resources = ['services', 'service_variants', 'packages', 'package_types', 'package_service', 'customer_packages', 'scholarships', 'test_offers', 'leads'];
        $models = array_map(fn ($name) => 'App\\Models\\'.$name, ['Service', 'ServiceVariant', 'Package', 'PackageType', 'CustomerPackage', 'Scholarship', 'TestOffer', 'Lead']);
        DB::table('audit_resource_settings')->whereIn('resource_key', $resources)->delete();
        DB::table('audit_logs')->whereIn('table_name', $resources)->orWhereIn('subject_type', $resources)->orWhereIn('auditable_type', $models)
            ->orWhere(fn ($query) => $query->where('table_name', 'audit_resource_settings')->whereIn('subject_label', $resources))->delete();
        $morphs = [...$resources, ...$models, 'service', 'service_variant', 'package', 'customer_package', 'scholarship', 'test_offer', 'lead'];
        DB::table('attachments')->whereIn('attachable_type', $morphs)->delete();
        DB::table('educational_bonus_sources')->whereIn('source_type', $morphs)->delete();
        DB::table('crm_interactions')->whereIn('contactable_type', ['lead', 'leads', 'App\\Models\\Lead'])->delete();
        DB::table('notifications')->where(function ($query): void {
            foreach (['package', 'service', 'lead'] as $key) {
                $query->orWhere('notification_key', 'like', $key.'.%');
            }
            foreach (['services', 'packages', 'educational-services', 'options/services', 'leads'] as $path) {
                $query->orWhere('go_to_url', '/'.$path)->orWhere('go_to_url', 'like', '/'.$path.'/%')->orWhere('go_to_url', 'like', '/'.$path.'?%');
            }
        })->delete();

        $fields = ['service_id', 'service_ids', 'service_variant_id', 'source_service_id', 'customer_package_id', 'package_id', 'package_type_id', 'package', 'packages', 'package_type', 'catalog_service', 'catalogue_service', 'service_scopes', 'company', 'company_id', 'invoice', 'invoice_id', 'invoice_amount', 'invoice_service_id', 'outsourcing_company_id', 'outsourcing_percentage', 'marketer_percentage', 'employment_type'];
        foreach (['contracts' => ['snapshot_data'], 'audit_logs' => ['old_values', 'new_values', 'changed_columns'], 'notifications' => ['data']] as $table => $columns) {
            DB::table($table)->select(['id', ...$columns])->orderBy('id')->chunkById(500, function ($rows) use ($table, $columns, $fields): void {
                foreach ($rows as $row) {
                    $updates = [];
                    foreach ($columns as $column) {
                        $payload = json_decode($row->$column ?? '', true);
                        if (! is_array($payload)) {
                            continue;
                        }
                        $cleaned = $column === 'changed_columns' ? array_values(array_diff($payload, $fields)) : $this->clean($payload, $fields);
                        if ($cleaned !== $payload) {
                            $updates[$column] = json_encode($cleaned, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                        }
                    }
                    if ($updates !== []) {
                        DB::table($table)->where('id', $row->id)->update($updates);
                    }
                }
            });
        }
    }

    private function dropColumns(string $table, array $columns): void
    {
        $columns = array_values(array_filter($columns, fn ($column) => Schema::hasColumn($table, $column)));
        if ($columns === []) {
            return;
        }
        foreach (Schema::getForeignKeys($table) as $key) {
            if (array_intersect($key['columns'], $columns)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropForeign(DB::getDriverName() === 'sqlite' ? $key['columns'] : $key['name']));
            }
        }
        foreach (Schema::getIndexes($table) as $index) {
            if (! $index['primary'] && array_intersect($index['columns'], $columns)) {
                Schema::table($table, function (Blueprint $blueprint) use ($index): void {
                    $index['unique'] ? $blueprint->dropUnique($index['name']) : $blueprint->dropIndex($index['name']);
                });
            }
        }
        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn($columns));
    }

    private function clean(array $payload, array $fields): array
    {
        foreach ($payload as $key => $value) {
            if (in_array($key, $fields, true)) {
                unset($payload[$key]);
            } elseif (is_array($value)) {
                $payload[$key] = $this->clean($value, $fields);
            }
        }

        return $payload;
    }

    public function down(): void
    {
        // Retired concepts and their discarded data are not reconstructed.
    }
};
