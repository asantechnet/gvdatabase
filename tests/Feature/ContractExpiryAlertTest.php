<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\ContractType;
use App\Models\Employee;
use App\Models\Language;
use App\Models\Notification;
use App\Models\Position;
use App\Services\ContractExpiryNotificationService;
use Carbon\CarbonImmutable;
use Database\Seeders\EmploymentContractSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractExpiryAlertTest extends TestCase
{
    use RefreshDatabase;

    private function employmentContract(array $attributes = []): Contract
    {
        $type = ContractType::query()->where('slug', 'employment')->firstOrFail();
        $template = ContractTemplate::factory()->create(['contract_type_id' => $type->id]);

        return Contract::factory()->create([
            'template_id' => $template->id,
            'contract_type_id' => $type->id,
            'employee_id' => Employee::factory()->create()->id,
            'status' => 'active',
            'is_cancelled' => false,
            'start_date' => '2026-01-01',
            'end_date' => '2026-08-20',
            ...$attributes,
        ]);
    }

    public function test_expiring_contracts_notify_their_owner_once_within_the_window(): void
    {
        $contract = $this->employmentContract(['end_date' => '2026-08-20']);
        $service = app(ContractExpiryNotificationService::class);
        $today = CarbonImmutable::parse('2026-08-01');

        $this->assertSame(1, $service->sendExpiringReminders($today));
        // Same deadline, same day: never sent twice.
        $this->assertSame(0, $service->sendExpiringReminders($today));

        $notification = Notification::query()
            ->where('notification_key', "contract.expiring.{$contract->id}.2026-08-20")
            ->sole();
        $this->assertSame('contract.expiring', $notification->data['translation_key']);
        $this->assertSame(19, $notification->data['days_remaining']);
        $this->assertSame('/employee-contracts?contractId='.$contract->id, $notification->go_to_url);
        $this->assertSame(1, $notification->recipients()->where('users.id', $contract->employee->user_id)->count());
    }

    public function test_contracts_outside_the_window_or_inactive_are_ignored(): void
    {
        $this->employmentContract(['end_date' => '2026-10-15']); // 75 days out
        $this->employmentContract(['end_date' => '2026-08-15', 'is_cancelled' => true]);
        $this->employmentContract(['end_date' => '2026-08-15', 'status' => 'terminated']);
        $this->employmentContract(['end_date' => null]);

        $this->assertSame(
            0,
            app(ContractExpiryNotificationService::class)->sendExpiringReminders(CarbonImmutable::parse('2026-08-01')),
        );
    }

    public function test_employment_contract_seeder_builds_templates_and_live_contracts(): void
    {
        Language::create(['name' => 'English', 'code' => 'en', 'is_active' => true]);
        $position = Position::factory()->create();
        $employee = Employee::factory()->create([
            'position_id' => $position->id,
            'join_date' => '2024-01-01',
            'contract_duration' => '1 year',
        ]);

        $this->seed(EmploymentContractSeeder::class);

        $contract = Contract::query()
            ->where('employee_id', $employee->id)
            ->whereNull('service')
            ->sole();
        $this->assertSame('active', $contract->status);
        $this->assertNotNull($contract->contract_number);
        $this->assertNotNull($contract->end_date);
        $this->assertStringNotContainsString('{{', $contract->content);
        $this->assertNotNull($contract->snapshot_data['parties'] ?? null);
        $this->assertSame(1, ContractTemplate::query()->where('position_id', $position->id)->count());
    }
}
