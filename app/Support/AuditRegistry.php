<?php

namespace App\Support;

use App\Models\Applicant;
use App\Models\ApplicantStage;
use App\Models\ApplicantUniversity;
use App\Models\Asset;
use App\Models\Attendance;
use App\Models\AuditResourceSetting;
use App\Models\Board;
use App\Models\CaseWin;
use App\Models\Comment;
use App\Models\CommissionAccrual;
use App\Models\CommissionSettlement;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractPolicy;
use App\Models\ContractService;
use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use App\Models\ContractType;
use App\Models\Country;
use App\Models\Currency;
use App\Models\CustomerPackage;
use App\Models\DailyReport;
use App\Models\EducationalBonusSource;
use App\Models\EducationLevel;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Invoice;
use App\Models\Language;
use App\Models\Lead;
use App\Models\Marketer;
use App\Models\Office;
use App\Models\OutsourcingAccrual;
use App\Models\OutsourcingSettlement;
use App\Models\Package;
use App\Models\PaidTimeOff;
use App\Models\PaymentDetail;
use App\Models\PendingTransaction;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Salary;
use App\Models\SalaryPayment;
use App\Models\Semester;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Subtask;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\University;
use App\Models\User;
use App\Models\Visitor;
use App\Models\WorthyEmployee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

final class AuditRegistry
{
    /** @return array<string, class-string<Model>> */
    public static function resources(): array
    {
        return [
            'applicants' => Applicant::class,
            'applicant_stages' => ApplicantStage::class,
            'applicant_universities' => ApplicantUniversity::class,
            'case_wins' => CaseWin::class,
            'attendances' => Attendance::class,
            'boards' => Board::class,
            'comments' => Comment::class,
            'companies' => Company::class,
            'commission_accruals' => CommissionAccrual::class,
            'commission_settlements' => CommissionSettlement::class,
            'contract_policies' => ContractPolicy::class,
            'contract_templates' => ContractTemplate::class,
            'contract_template_versions' => ContractTemplateVersion::class,
            'contract_types' => ContractType::class,
            'contracts' => Contract::class,
            'contract_services' => ContractService::class,
            'currencies' => Currency::class,
            'countries' => Country::class,
            'customer_packages' => CustomerPackage::class,
            'daily_reports' => DailyReport::class,
            'education_levels' => EducationLevel::class,
            'educational_bonus_sources' => EducationalBonusSource::class,
            'employees' => Employee::class,
            'expenses' => Expense::class,
            'incomes' => Income::class,
            'invoices' => Invoice::class,
            'languages' => Language::class,
            'leads' => Lead::class,
            'marketers' => Marketer::class,
            'offices' => Office::class,
            'outsourcing_accruals' => OutsourcingAccrual::class,
            'outsourcing_settlements' => OutsourcingSettlement::class,
            'leaves' => PaidTimeOff::class,
            'payment_details' => PaymentDetail::class,
            'packages' => Package::class,
            'pending_transactions' => PendingTransaction::class,
            'permissions' => Permission::class,
            'positions' => Position::class,
            'salaries' => Salary::class,
            'salary_payments' => SalaryPayment::class,
            'services' => Service::class,
            'semesters' => Semester::class,
            'settings' => Setting::class,
            'assets' => Asset::class,
            'subtasks' => Subtask::class,
            'tags' => Tag::class,
            'task_attachments' => TaskAttachment::class,
            'tasks' => Task::class,
            'users' => User::class,
            'universities' => University::class,
            'visitors' => Visitor::class,
            'worthy_employees' => WorthyEmployee::class,
        ];
    }

    public static function keyFor(Model|string $model): string
    {
        $class = is_string($model) ? $model : $model::class;

        return array_search($class, self::resources(), true) ?: 'other';
    }

    public static function enabled(Model $model): bool
    {
        $key = self::keyFor($model);
        if ($key === 'other' || ! Schema::hasTable('audit_resource_settings')) {
            return false;
        }

        return AuditResourceSetting::query()->where('resource_key', $key)->value('enabled') ?? false;
    }

    public static function ensureSettings(): void
    {
        $now = now();
        AuditResourceSetting::query()->insertOrIgnore(array_map(fn (string $key) => [
            'resource_key' => $key,
            'enabled' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], array_keys(self::resources())));
    }

    public static function subjectLabel(Model $model): string
    {
        $key = self::keyFor($model);
        $contextLabel = match ($key) {
            'attendances' => self::joinedLabel(self::relatedLabel($model, 'employee'), $model->getRawOriginal('date')),
            'leaves' => self::joinedLabel(self::relatedLabel($model, 'employee'), $model->getRawOriginal('date')),
            'daily_reports' => self::joinedLabel(self::relatedLabel($model, 'user'), $model->getRawOriginal('report_date')),
            'contract_services' => self::joinedLabel(self::relatedLabel($model, 'contract'), self::relatedLabel($model, 'service')),
            'customer_packages' => self::joinedLabel(self::relatedLabel($model, 'applicant'), self::relatedLabel($model, 'package')),
            'case_wins' => self::joinedLabel(self::relatedLabel($model, 'employee'), self::relatedLabel($model, 'applicant')),
            'comments' => self::relatedLabel($model, 'task'),
            'worthy_employees' => self::joinedLabel(self::relatedLabel($model, 'employee'), $model->getAttribute('bonus_month')),
            'commission_accruals' => self::joinedLabel(self::relatedLabel($model, 'employee'), $model->getAttribute('earned_at')),
            'commission_settlements' => self::joinedLabel(self::relatedLabel($model, 'employee'), $model->getAttribute('settlement_number')),
            'outsourcing_accruals' => self::joinedLabel(self::relatedLabel($model, 'company'), $model->getAttribute('earned_at')),
            'outsourcing_settlements' => self::joinedLabel(self::relatedLabel($model, 'company'), $model->getAttribute('settlement_number')),
            default => '',
        };
        if ($contextLabel !== '') {
            return mb_substr($contextLabel, 0, 255);
        }

        $candidates = [
            'invoices' => ['invoice_number', 'customer_company', 'deptor'],
            'contracts' => ['contract_number', 'name', 'counterparty'],
            'incomes' => ['receipt_number', 'description', 'type'],
            'salaries' => ['payslip_number', 'salary_month'],
            'commission_settlements' => ['settlement_number', 'period_start'],
            'commission_accruals' => ['earned_at', 'status'],
            'outsourcing_settlements' => ['settlement_number', 'period_start'],
            'outsourcing_accruals' => ['earned_at', 'status'],
            'assets' => ['serial_number', 'item', 'model'],
            'companies' => ['trade_name', 'legal_name', 'registration_number'],
            'positions' => ['title'],
            'boards' => ['title'],
            'tasks' => ['title'],
            'subtasks' => ['title'],
            'task_attachments' => ['original_name'],
            'contract_policies' => ['label', 'header'],
            'services' => ['type'],
            'packages' => ['name'],
            'customer_packages' => ['purchased_at', 'status'],
            'case_wins' => ['won_at', 'stage_key'],
            'payment_details' => ['method'],
            'daily_reports' => ['report_date'],
            'attendances' => ['date'],
            'leaves' => ['date', 'duration_days', 'reason', 'approval_note'],
            'contract_services' => ['notes'],
            'worthy_employees' => ['bonus_month', 'selected_type'],
            'settings' => ['short_company_name', 'website_title'],
        ][$key] ?? ['name', 'title', 'code', 'serial_number', 'email', 'date', 'type'];

        foreach ($candidates as $attribute) {
            $value = $model->getAttribute($attribute);
            if (is_scalar($value) && trim((string) $value) !== '') {
                return mb_substr(trim((string) $value), 0, 255);
            }
        }

        foreach (['employee', 'applicant', 'user', 'task'] as $relation) {
            if (! method_exists($model, $relation)) {
                continue;
            }
            $related = $model->{$relation}()->withTrashed()->first();
            if ($related) {
                $label = self::subjectLabel($related);
                if ($label !== '') {
                    return $label;
                }
            }
        }

        return '';
    }

    private static function relatedLabel(Model $model, string $relation): string
    {
        if (! method_exists($model, $relation)) {
            return '';
        }

        $related = $model->{$relation}()->withTrashed()->first();

        return $related ? self::subjectLabel($related) : '';
    }

    private static function joinedLabel(mixed ...$parts): string
    {
        return collect($parts)
            ->filter(fn ($part) => is_scalar($part) && trim((string) $part) !== '')
            ->map(fn ($part) => trim((string) $part))
            ->implode(' · ');
    }
}
