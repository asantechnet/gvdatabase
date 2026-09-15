<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\ContractType;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Office;
use App\Models\PaymentDetail;
use App\Models\Position;
use App\Models\Service;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

final class AuditPresenter
{
    /** @var array<string, string|null> */
    private array $relatedLabels = [];

    private const HIDDEN_FIELDS = [
        'id', 'created_at', 'updated_at', 'deleted_at', 'remember_token', 'password',
        'mail_password', 'two_factor_secret', 'calculation_hash', 'calculation_inputs',
        'rule_snapshot', 'snapshot_data', 'account_settings', 'disk', 'path', 'mime_type',
        'url', 'method', 'ip_address', 'logged_at', 'calculated_at', 'guard_name', 'slug',
        'order', 'currency_snapshot_source', 'currency_snapshot_note',
    ];

    /** @var array<string, class-string<Model>> */
    private const RELATED_MODELS = [
        'employee_id' => Employee::class,
        'staff_id' => Employee::class,
        'user_id' => User::class,
        'requested_by' => User::class,
        'granted_by' => User::class,
        'quality_reviewed_by' => User::class,
        'customer_id' => Customer::class,
        'invoice_id' => Invoice::class,
        'currency_id' => Currency::class,
        'office_id' => Office::class,
        'position_id' => Position::class,
        'service_id' => Service::class,
        'task_id' => Task::class,
        'source_task_id' => Task::class,
        'template_id' => ContractTemplate::class,
        'contract_type_id' => ContractType::class,
        'contract_id' => Contract::class,
        'company_id' => Company::class,
        'payment_detail_id' => PaymentDetail::class,
    ];

    public function present(AuditLog $audit): array
    {
        $audit->loadMissing(['user:id,name,email', 'employee:id,name', 'auditor:id,name,email']);
        $model = $this->auditedModel($audit);
        $resourceKey = $audit->subject_type ?: AuditRegistry::keyFor($audit->auditable_type);
        $blockingLaterAuditId = $this->blockingLaterAuditId($audit);

        return [
            'id' => $audit->id,
            'event' => $audit->event,
            'resource_key' => $resourceKey,
            'subject_label' => $this->subjectLabel($audit, $model),
            'changes' => $audit->event === 'created' ? [] : $this->changes($audit),
            'changed_by' => $this->person($audit->employee?->name ?: $audit->user?->name, $audit->user_id),
            'audited_by' => $this->person($audit->auditor?->name, $audit->audited_by),
            'audit_state' => $audit->audited_at ? 'audited' : 'pending',
            'resolution' => $audit->review_status,
            'audited_at' => $audit->audited_at?->toISOString(),
            'created_at' => $audit->created_at?->toISOString(),
            'can_revert' => ! $audit->audited_at
                && in_array($audit->event, ['updated', 'deleted'], true)
                && $blockingLaterAuditId === null,
            'revert_blocked_by_id' => $blockingLaterAuditId,
        ];
    }

    private function person(?string $name, ?int $id): ?array
    {
        return $name ? ['id' => $id, 'name' => $name] : null;
    }

    private function blockingLaterAuditId(AuditLog $audit): ?int
    {
        return AuditLog::query()
            ->where('auditable_type', $audit->auditable_type)
            ->where('auditable_id', $audit->auditable_id)
            ->where('id', '>', $audit->id)
            ->where(fn ($query) => $query->whereNull('review_status')->orWhere('review_status', '!=', 'discarded'))
            ->orderByDesc('id')
            ->value('id');
    }

    private function changes(AuditLog $audit): array
    {
        $old = $audit->old_values ?? [];
        $new = $audit->new_values ?? [];
        $fields = array_values(array_unique([...(array) $audit->changed_columns, ...array_keys($old), ...array_keys($new)]));
        $changes = [];

        foreach ($fields as $field) {
            if ($this->hidden((string) $field)) {
                continue;
            }

            $changes[] = [
                'field' => $this->friendlyFieldKey((string) $field),
                'before' => $this->safeValue((string) $field, $old[$field] ?? null),
                'after' => $this->safeValue((string) $field, $new[$field] ?? null),
            ];
        }

        return $changes;
    }

    private function hidden(string $field): bool
    {
        return in_array($field, self::HIDDEN_FIELDS, true)
            || Str::contains(Str::lower($field), [
                'password', 'secret', 'token', 'hash', '_file', 'avatar', 'logo', 'signature',
                'letterhead', 'cover_design', 'mail_',
            ])
            || Str::endsWith($field, ['_latitude', '_longitude', '_accuracy_meters', '_distance_meters']);
    }

    private function friendlyFieldKey(string $field): string
    {
        return match ($field) {
            'staff_id', 'employee_id' => 'employee',
            'user_id', 'requested_by', 'granted_by', 'quality_reviewed_by' => 'user',
            'customer_id' => 'customer',
            'invoice_id' => 'invoice',
            'currency_id' => 'currency',
            'office_id' => 'office',
            'position_id' => 'position',
            'service_id' => 'service',
            'task_id', 'source_task_id' => 'task',
            'template_id' => 'contractTemplate',
            'contract_type_id' => 'contractType',
            'company_id' => 'company',
            'payment_detail_id' => 'paymentMethod',
            default => Str::camel($field),
        };
    }

    private function safeValue(string $field, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (isset(self::RELATED_MODELS[$field]) && is_numeric($value)) {
            return $this->relatedLabel(self::RELATED_MODELS[$field], (int) $value);
        }

        if (is_string($value) && mb_strlen($value) > 500) {
            return mb_substr(strip_tags($value), 0, 500).'…';
        }

        if (is_array($value)) {
            return collect($value)
                ->reject(fn ($item, $key) => $this->hidden((string) $key))
                ->map(fn ($item, $key) => is_string($item) && mb_strlen($item) > 200
                    ? mb_substr(strip_tags($item), 0, 200).'…'
                    : $item)
                ->values()
                ->all();
        }

        return $value;
    }

    /** @param class-string<Model> $class */
    private function relatedLabel(string $class, int $id): ?string
    {
        $cacheKey = $class.':'.$id;
        if (array_key_exists($cacheKey, $this->relatedLabels)) {
            return $this->relatedLabels[$cacheKey];
        }

        try {
            $query = $class::query();
            if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($class), true)) {
                $query->withTrashed();
            }
            $model = $query->find($id);

            return $this->relatedLabels[$cacheKey] = $model ? (AuditRegistry::subjectLabel($model) ?: null) : null;
        } catch (Throwable) {
            return $this->relatedLabels[$cacheKey] = null;
        }
    }

    private function auditedModel(AuditLog $audit): ?Model
    {
        $class = $audit->auditable_type;
        if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            return null;
        }

        try {
            $query = $class::query();
            if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($class), true)) {
                $query->withTrashed();
            }

            return $query->find($audit->auditable_id);
        } catch (Throwable) {
            return null;
        }
    }

    private function subjectLabel(AuditLog $audit, ?Model $model): string
    {
        if (trim((string) $audit->subject_label) !== '') {
            return trim((string) $audit->subject_label);
        }
        if ($model && ($label = AuditRegistry::subjectLabel($model)) !== '') {
            return $label;
        }

        $values = [...($audit->new_values ?? []), ...($audit->old_values ?? [])];
        foreach (['invoice_number', 'contract_number', 'receipt_number', 'payslip_number', 'serial_number', 'name', 'title', 'label', 'code', 'original_name', 'date'] as $field) {
            if (isset($values[$field]) && is_scalar($values[$field]) && trim((string) $values[$field]) !== '') {
                return mb_substr(trim((string) $values[$field]), 0, 255);
            }
        }

        return '';
    }
}
