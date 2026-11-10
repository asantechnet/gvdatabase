<?php

namespace App\Support;

use App\Models\AcademyApplicantDetail;
use App\Models\AcademyClass;
use App\Models\Announcement;
use App\Models\Applicant;
use App\Models\ApplicantAttachment;
use App\Models\ApplicantContract;
use App\Models\ApplicantStage;
use App\Models\ApplicantUniversity;
use App\Models\Asset;
use App\Models\Attachment;
use App\Models\Attendance;
use App\Models\AuditResourceSetting;
use App\Models\Board;
use App\Models\CaseWin;
use App\Models\Comment;
use App\Models\Contract;
use App\Models\ContractPolicy;
use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use App\Models\ContractType;
use App\Models\Country;
use App\Models\Currency;
use App\Models\DailyReport;
use App\Models\EducationalBonusSource;
use App\Models\EducationalScholarship;
use App\Models\EducationLevel;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Language;
use App\Models\OffDay;
use App\Models\Office;
use App\Models\LeaveRequest;
use App\Models\Permission;
use App\Models\Position;
use App\Models\PrivateApplicantDetail;
use App\Models\Program;
use App\Models\Salary;
use App\Models\SalaryPayment;
use App\Models\ScholarshipApplicantDetail;
use App\Models\Semester;
use App\Models\Setting;
use App\Models\Skill;
use App\Models\Subject;
use App\Models\Subtask;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\Test;
use App\Models\TestApplicantDetail;
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
            // Per-service applicant details and the catalogues they reference.
            'private_applicant_details' => PrivateApplicantDetail::class,
            'educational_scholarships' => EducationalScholarship::class,
            'academy_classes' => AcademyClass::class,
            'scholarship_applicant_details' => ScholarshipApplicantDetail::class,
            'test_applicant_details' => TestApplicantDetail::class,
            'academy_applicant_details' => AcademyApplicantDetail::class,
            'subjects' => Subject::class,
            'tests' => Test::class,
            'skills' => Skill::class,
            'programs' => Program::class,
            'applicant_attachments' => ApplicantAttachment::class,
            'applicant_stages' => ApplicantStage::class,
            'applicant_universities' => ApplicantUniversity::class,
            'case_wins' => CaseWin::class,
            'attendances' => Attendance::class,
            'boards' => Board::class,
            'comments' => Comment::class,
            'contract_policies' => ContractPolicy::class,
            'contract_templates' => ContractTemplate::class,
            'contract_template_versions' => ContractTemplateVersion::class,
            'contract_types' => ContractType::class,
            'contracts' => Contract::class,
            'applicant_contracts' => ApplicantContract::class,
            'currencies' => Currency::class,
            'countries' => Country::class,
            'daily_reports' => DailyReport::class,
            'education_levels' => EducationLevel::class,
            'educational_bonus_sources' => EducationalBonusSource::class,
            'employees' => Employee::class,
            'employee_advances' => EmployeeAdvance::class,
            'expenses' => Expense::class,
            'incomes' => Income::class,
            'languages' => Language::class,
            'offices' => Office::class,
            'off_days' => OffDay::class,
            'leaves' => LeaveRequest::class,
            'permissions' => Permission::class,
            'positions' => Position::class,
            'salaries' => Salary::class,
            'salary_payments' => SalaryPayment::class,
            'semesters' => Semester::class,
            'settings' => Setting::class,
            'assets' => Asset::class,
            'attachments' => Attachment::class,
            'subtasks' => Subtask::class,
            'tags' => Tag::class,
            'task_attachments' => TaskAttachment::class,
            'tasks' => Task::class,
            'users' => User::class,
            'announcements' => Announcement::class,
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
            // Auditing is opt-in: a new resource stays unaudited until an
            // administrator enables it on the audit settings screen.
            'enabled' => false,
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
            'applicant_contracts' => self::joinedLabel(self::relatedLabel($model, 'contract'), $model->serviceLabel()),
            'case_wins' => self::joinedLabel(self::relatedLabel($model, 'employee'), self::relatedLabel($model, 'applicant')),
            'comments' => self::relatedLabel($model, 'task'),
            'worthy_employees' => self::joinedLabel(self::relatedLabel($model, 'employee'), $model->getAttribute('bonus_month')),
            default => '',
        };
        if ($contextLabel !== '') {
            return mb_substr($contextLabel, 0, 255);
        }

        $candidates = [
            'contracts' => ['contract_number', 'name', 'counterparty'],
            'incomes' => ['receipt_number', 'description', 'type'],
            'salaries' => ['payslip_number', 'salary_month'],
            'assets' => ['serial_number', 'item', 'model'],
            'positions' => ['title'],
            'boards' => ['title'],
            'tasks' => ['title'],
            'subtasks' => ['title'],
            'task_attachments' => ['original_name'],
            'attachments' => ['original_name'],
            'contract_policies' => ['label', 'header'],
            'subjects' => ['name'],
            'tests' => ['name'],
            'skills' => ['name'],
            'programs' => ['name'],
            'times' => ['name'],
            'durations' => ['name'],
            'teachers' => ['name', 'email', 'contact'],
            'case_wins' => ['won_at', 'stage_key'],
            'daily_reports' => ['report_date'],
            'attendances' => ['date'],
            'leaves' => ['date', 'duration_days', 'off_day_days', 'reason', 'approval_note'],
            'applicant_contracts' => ['notes'],
            'worthy_employees' => ['bonus_month', 'selected_type'],
            'settings' => ['short_company_name', 'website_title'],
            'announcements' => ['title', 'message'],
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
