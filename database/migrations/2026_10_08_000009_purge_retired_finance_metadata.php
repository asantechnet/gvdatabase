<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $resources = [
            'companies', 'invoices', 'invoice_service', 'invoice_services', 'payment_details',
            'commission_accruals', 'commission_settlements', 'outsourcing_accruals',
            'outsourcing_settlements', 'employee_service_commissions',
        ];
        $models = array_map(fn (string $name) => 'App\\Models\\'.$name, [
            'Company', 'Invoice', 'InvoiceService', 'PaymentDetail', 'CommissionAccrual',
            'CommissionSettlement', 'OutsourcingAccrual', 'OutsourcingSettlement',
        ]);

        DB::table('audit_resource_settings')->whereIn('resource_key', $resources)->delete();
        DB::table('audit_logs')->where(function ($query) use ($resources, $models): void {
            $query->whereIn('table_name', $resources)
                ->orWhereIn('subject_type', $resources)
                ->orWhereIn('auditable_type', $models)
                ->orWhere(function ($scope) use ($resources): void {
                    $scope->where('table_name', 'audit_resource_settings')->whereIn('subject_label', $resources);
                });
        })->delete();

        DB::table('notifications')->where(function ($query): void {
            foreach (['invoice', 'company', 'commission', 'outsourcing'] as $key) {
                $query->orWhere('notification_key', 'like', $key.'.%');
            }
            foreach (['invoices', 'companies', 'payment-details', 'commissions', 'commission-settlements', 'outsourcing'] as $path) {
                $query->orWhere('go_to_url', '/'.$path)
                    ->orWhere('go_to_url', 'like', '/'.$path.'/%')
                    ->orWhere('go_to_url', 'like', '/'.$path.'?%');
            }
        })->delete();

        $removedFields = [
            'invoice', 'invoice_id', 'invoice_amount', 'invoice_service_id', 'company_id',
            'outsourcing_company_id', 'outsourcing_percentage', 'marketer_percentage',
            'snapshot_commission_percentage', 'snapshot_marketer_commission_percentage',
            'snapshot_outsourcing_percentage', 'employment_type',
        ];
        DB::table('audit_logs')->select(['id', 'old_values', 'new_values', 'changed_columns'])
            ->orderBy('id')->chunkById(500, function ($rows) use ($removedFields): void {
                foreach ($rows as $row) {
                    $updates = [];
                    foreach (['old_values', 'new_values', 'changed_columns'] as $column) {
                        $payload = json_decode($row->$column ?? '', true);
                        if (! is_array($payload)) {
                            continue;
                        }
                        $cleaned = $column === 'changed_columns'
                            ? array_values(array_diff($payload, $removedFields))
                            : $this->removeFields($payload, $removedFields);
                        if ($cleaned !== $payload) {
                            $updates[$column] = json_encode($cleaned, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                        }
                    }
                    if ($updates !== []) {
                        DB::table('audit_logs')->where('id', $row->id)->update($updates);
                    }
                }
            });
    }

    private function removeFields(array $payload, array $fields): array
    {
        foreach ($payload as $key => $value) {
            if (in_array($key, $fields, true)) {
                unset($payload[$key]);
            } elseif (is_array($value)) {
                $payload[$key] = $this->removeFields($value, $fields);
            }
        }

        return $payload;
    }

    public function down(): void
    {
        // Retired metadata and discarded records are intentionally not restored.
    }
};
