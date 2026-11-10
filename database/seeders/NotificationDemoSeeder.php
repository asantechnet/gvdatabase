<?php

namespace Database\Seeders;

use App\Models\Applicant;
use App\Models\ApplicantContract;
use App\Models\ApplicantStage;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use App\Models\ContractType;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\Language;
use App\Models\Office;
use App\Models\Position;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\Contracts\BuiltInContractTemplateService;
use App\Services\Contracts\PlaceholderRegistry;
use App\Services\EducationalWorkflowNotificationService;
use App\Support\BuiltInService;
use App\Support\RoleHierarchy;
use App\Support\ServiceBoard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Standalone fixture for exercising real-time notification delivery.
 *
 * Run it on its own — it is deliberately **not** part of {@see DatabaseSeeder}:
 *
 *     php artisan db:seed --class=NotificationDemoSeeder
 *
 * It seeds one validated staff account that holds both specialties at once, and
 * builds the records that the notification producers react to:
 *
 *  - the account is the applicant's responsible (counselor) employee and its
 *    admission employee, so both case notifications are delivered to it;
 *  - it is the assignee of a task, so `taskAssigned` reaches it;
 *  - a primary contract plus a fresh extension contract are issued for the case,
 *    so `contractGenerated` reaches it.
 *
 * Re-running the seeder queues a *fresh* task and extension contract every time
 * (the state-only records and their notifications are created once), which is
 * what lets you watch a signed-in session receive a new batch: the frontend
 * polls `/api/notifications/heartbeat` every 30 seconds.
 */
class NotificationDemoSeeder extends Seeder
{
    /** The account to sign in with while watching the heartbeat. */
    public const DEMO_EMAIL = 'notify.demo@example.test';

    /** Deliberately fixed demo credential; never run this against production. */
    public const DEMO_PASSWORD = 'password';

    /** The demo case the seeded task/contracts belong to. */
    public const APPLICANT_EMAIL = 'realtime.demo.applicant@example.test';

    private const DEMO_NAME = 'Realtime Demo Staff';

    private const BOARD_TITLE = 'Realtime Notification Checks';

    public const TASK_TITLE_PREFIX = 'Realtime notification check #';

    private const EXTENSION_PREFIX = 'Realtime notification extension #';

    private const TASK_DUE_DAYS = 3;

    public function run(): void
    {
        // Every dependency is idempotent, so the seeder is safe to run on a
        // freshly migrated database and safe to re-run on a populated one.
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            RolePermissionSeeder::class,
            PositionSeeder::class,
            OfficeSeeder::class,
            CurrencySeeder::class,
            EducationalReferenceSeeder::class,
            ContractTemplateVersionSeeder::class,
        ]);

        [$user, $employee] = $this->demoStaff();

        $notifications = app(EducationalWorkflowNotificationService::class);

        // State-level records: created once and reported once.
        $applicant = $this->applicantCase($employee);
        $primary = $this->primaryContract($applicant, $employee);

        $notifications->applicantAssigned($applicant);
        $notifications->admissionAssigned($applicant);
        $notifications->contractGenerated($primary);

        // Fresh on every run: the CLI has no actor, so nothing is filtered out
        // of the assignee list.
        $task = $this->freshTask($applicant, $user);
        $notifications->taskAssigned($task, [$user->id]);

        $extension = $this->freshExtensionContract($applicant, $employee, $primary);
        $notifications->contractGenerated($extension);

        $this->report($user, $applicant, $task, $primary, $extension);
    }

    /**
     * One validated account that is a counselor *and* an admission employee.
     *
     * `AgencyStaffAssignment` accepts an employee for a specialty through its
     * position **or** through a matching access role, so a single employee with
     * the counselor position plus the counselor/admission roles is selectable in
     * both specialist pickers and receives both case notifications.
     *
     * @return array{0: User, 1: Employee}
     */
    private function demoStaff(): array
    {
        $roleIds = [];
        foreach (['counselor', 'admission'] as $roleName) {
            $roleIds[] = Role::query()->firstOrCreate(
                ['name' => $roleName],
                ['level' => RoleHierarchy::LEVELS[$roleName] ?? RoleHierarchy::LEVELS['counselor']],
            )->id;
        }

        $user = User::withTrashed()->firstOrNew(['email' => self::DEMO_EMAIL]);
        $user->fill([
            'name' => self::DEMO_NAME,
            'phone' => '+93 700 000 123',
            'address' => 'Kabul, Afghanistan',
            'acc_status' => 'active',
            'email_verified_at' => now(),
            'must_set_password' => false,
        ]);
        $user->password = Hash::make(self::DEMO_PASSWORD);
        $user->deleted_at = null;
        $user->save();
        $user->roles()->syncWithoutDetaching($roleIds);

        $positionIds = Position::query()->pluck('id', 'system_key');

        $employee = Employee::withTrashed()->firstOrNew(['user_id' => $user->id]);
        $employee->fill([
            'name' => $user->name,
            'father_name' => 'N/A',
            'contact' => $user->phone,
            'addr' => $user->address,
            'education_lvl' => 'Bachelor',
            'position_id' => $positionIds['counselor'] ?? null,
            'status' => Employee::STATUS_ACTIVE,
            'office_id' => Office::query()->where('code', 'HQ')->value('id'),
            'salary' => 60000,
            'join_date' => now()->toDateString(),
            'contract_duration' => 'Permanent',
        ]);
        $employee->deleted_at = null;
        $employee->save();

        // Both specialties are also recorded on the account's position history,
        // which is what the staff assignment screens list.
        $user->positions()->syncWithoutDetaching(
            collect(['counselor', 'admission'])
                ->map(fn (string $key) => $positionIds[$key] ?? null)
                ->filter()
                ->mapWithKeys(fn ($id) => [$id => ['assigned_at' => now()->toDateString()]])
                ->all(),
        );

        return [$user, $employee];
    }

    /** The case the seeded task and contracts belong to. */
    private function applicantCase(Employee $employee): Applicant
    {
        $applicant = Applicant::withTrashed()->firstOrNew(['email' => self::APPLICANT_EMAIL]);
        $applicant->fill([
            'service' => BuiltInService::SCHOLARSHIP,
            'name' => 'Realtime Notification Case',
            'father_name' => 'Demo Guardian',
            'father_phone' => '+93 700 000 124',
            'contact' => '+93 700 000 125',
            'address' => 'Kabul, Afghanistan',
            'responsible_employee_id' => $employee->id,
            'current_stage_id' => ApplicantStage::query()->where('key', 'consultation')->value('id'),
            'status' => 'active',
            'notes' => 'Demo case created by NotificationDemoSeeder for real-time notification testing.',
        ]);
        $applicant->deleted_at = null;
        $this->saveIfDirty($applicant);
        $applicant->responsibleEmployees()->sync([$employee->id]);

        $detail = $applicant->scholarshipDetail()->withTrashed()->firstOrCreate([]);
        if ($detail->trashed()) {
            $detail->restore();
        }

        return $applicant;
    }

    /** The case's primary contract, issued once. */
    private function primaryContract(Applicant $applicant, Employee $employee): Contract
    {
        $template = app(BuiltInContractTemplateService::class)->ensure(BuiltInService::SCHOLARSHIP);
        $version = $this->activeVersion($template);
        $type = ContractType::query()->where('slug', 'customer')->first();

        $contract = Contract::withTrashed()->firstOrNew([
            'applicant_id' => $applicant->id,
            'service' => BuiltInService::SCHOLARSHIP,
            'contract_role' => Contract::ROLE_PRIMARY,
        ]);
        $contract->fill([
            'template_id' => $template->id,
            'contract_template_version_id' => $version?->id,
            'contract_type_id' => $type?->id,
            'contract_language_id' => $version?->language_id,
            'sold_by_employee_id' => $employee->id,
            'name' => $template->name,
            'type' => $type?->name,
            'cover_text' => $template->cover_text,
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'company_signatory_name' => PlaceholderRegistry::ourCompanyValues()['name'],
            'counterparty' => $applicant->name,
            'counterparty_email' => $applicant->email,
            'counterparty_contact' => $applicant->contact,
            'counterparty_address' => $applicant->address,
            'is_custom' => false,
            'is_cancelled' => false,
        ]);
        $contract->deleted_at = null;
        $this->saveIfDirty($contract);

        $this->serviceLine($contract, $applicant, $employee, 50000.0);

        return $contract;
    }

    /** A numbered task assigned to the demo account — the repeatable event. */
    private function freshTask(Applicant $applicant, User $user): Task
    {
        $board = ServiceBoard::firstOrCreateFor(self::BOARD_TITLE);
        $sequence = Task::withTrashed()
            ->where('board_id', $board->id)
            ->where('title', 'like', self::TASK_TITLE_PREFIX.'%')
            ->count() + 1;

        $task = Task::create([
            'board_id' => $board->id,
            'applicant_id' => $applicant->id,
            'title' => self::TASK_TITLE_PREFIX.$sequence,
            'description' => 'Seeded by NotificationDemoSeeder to trigger a real-time task assignment.',
            'priority' => 'high',
            'progress' => 0,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(self::TASK_DUE_DAYS)->toDateString(),
            'order' => (Task::withTrashed()->where('board_id', $board->id)->max('order') ?? -1) + 1,
        ]);

        $task->users()->sync([$user->id]);
        $board->users()->syncWithoutDetaching([$user->id]);

        return $task;
    }

    /**
     * A numbered extension contract on the demo case — the second repeatable
     * event, covering the contract producer.
     */
    private function freshExtensionContract(Applicant $applicant, Employee $employee, Contract $primary): Contract
    {
        $sequence = Contract::withTrashed()
            ->where('applicant_id', $applicant->id)
            ->where('name', 'like', self::EXTENSION_PREFIX.'%')
            ->count() + 1;

        $contract = Contract::create([
            'applicant_id' => $applicant->id,
            'service' => BuiltInService::SCHOLARSHIP,
            'contract_role' => Contract::ROLE_EXTENSION,
            'parent_contract_id' => $primary->id,
            'template_id' => $primary->template_id,
            'contract_template_version_id' => $primary->contract_template_version_id,
            'contract_type_id' => $primary->contract_type_id,
            'contract_language_id' => $primary->contract_language_id,
            'sold_by_employee_id' => $employee->id,
            'name' => self::EXTENSION_PREFIX.$sequence,
            'type' => $primary->type,
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'company_signatory_name' => $primary->company_signatory_name,
            'counterparty' => $applicant->name,
            'counterparty_email' => $applicant->email,
            'counterparty_contact' => $applicant->contact,
            'counterparty_address' => $applicant->address,
            'is_custom' => false,
            'is_cancelled' => false,
        ]);

        $this->serviceLine($contract, $applicant, $employee, 1000.0);

        return $contract;
    }

    /** The service line a contract cannot be shown without. */
    private function serviceLine(Contract $contract, Applicant $applicant, Employee $employee, float $fee): ApplicantContract
    {
        $currency = Currency::query()->where('is_base', true)->first() ?? Currency::query()->first();

        // The line starts on its service's `new` stage like a generated one, so
        // the pipeline and the per-line move control work on this fixture.
        $stageId = ApplicantStage::query()
            ->where('key', 'new')->where('is_active', true)->where('service', BuiltInService::SCHOLARSHIP)
            ->orderByRaw('country_id IS NULL')->orderByRaw('university_id IS NULL')
            ->value('id') ?? $applicant->current_stage_id;

        $line = ApplicantContract::withTrashed()->firstOrNew([
            'contract_id' => $contract->id,
            'service' => BuiltInService::SCHOLARSHIP,
        ]);
        $line->fill([
            'applicant_id' => $applicant->id,
            'fee' => $fee,
            'discount' => 0,
            'currency_id' => $currency?->id,
            'currency_rate' => 1,
            'sold_by_employee_id' => $employee->id,
            'current_stage_id' => $stageId,
            'notes' => 'Realtime notification demo service line',
        ]);
        $line->deleted_at = null;
        $this->saveIfDirty($line);

        return $line;
    }

    /** The active, current version of the built-in template for the seeded language. */
    private function activeVersion(ContractTemplate $template): ?ContractTemplateVersion
    {
        $languageId = Language::query()->where('code', 'en')->value('id');

        if (! $languageId) {
            return null;
        }

        return ContractTemplateVersion::withTrashed()
            ->where('contract_template_id', $template->id)
            ->where('language_id', $languageId)
            ->where('status', 'active')
            ->where('is_current', true)
            ->orderBy('id')
            ->first();
    }

    /**
     * Persist only when something actually changed.
     *
     * The state-level producers key their notification on the record (or, for
     * the admission assignment, on its `updated_at`), so re-saving an unchanged
     * row on every run would keep re-notifying events that did not happen.
     */
    private function saveIfDirty(Model $model): void
    {
        if (! $model->exists || $model->isDirty()) {
            $model->save();
        }
    }

    private function report(User $user, Applicant $applicant, Task $task, Contract $primary, Contract $extension): void
    {
        $this->command->info('Real-time notification demo ready.');
        $this->command->info('  Sign in as : '.self::DEMO_EMAIL.'  /  '.self::DEMO_PASSWORD);
        $this->command->info('  Staff      : '.$user->name.' (counselor + admission, roles and position history)');
        $this->command->info('  Case       : #'.$applicant->id.' '.$applicant->name.' — responsible and admission employee are the same account');
        $this->command->info('  Task       : #'.$task->id.' '.$task->title.' (assignee: '.self::DEMO_EMAIL.')');
        $this->command->info('  Contracts  : '.$primary->contract_number.' (primary) + '.$extension->contract_number.' (extension)');
        $this->command->info('  Queued for the heartbeat: task assigned, contract created, extension created, case assigned, admission assigned.');
        $this->command->info('  Sign in, then re-run this seeder to queue a fresh task + extension; the client polls /api/notifications/heartbeat every 30s.');
    }
}
