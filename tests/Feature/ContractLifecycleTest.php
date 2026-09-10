<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\ContractType;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use App\Services\Contracts\ContractLifecycleService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContractLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_lifecycle_expires_ended_contracts_and_sends_idempotent_escalating_reminders(): void
    {
        $manager = User::factory()->create();
        $manager->roles()->attach(Role::query()->where('name', 'manager')->firstOrFail());
        $today = CarbonImmutable::parse('2026-09-08');

        $ended = Contract::factory()->create([
            'status' => 'active',
            'start_date' => '2025-09-07',
            'end_date' => '2026-09-07',
        ]);
        $dueSoon = Contract::factory()->create([
            'status' => 'active',
            'start_date' => '2025-09-22',
            'end_date' => '2026-09-22',
        ]);
        $endsToday = Contract::factory()->create([
            'status' => 'active',
            'start_date' => '2025-09-08',
            'end_date' => '2026-09-08',
        ]);

        $service = app(ContractLifecycleService::class);
        $first = $service->process($today);
        $second = $service->process($today);

        $this->assertSame(['expired' => 1, 'reminders' => 2], $first);
        $this->assertSame(['expired' => 0, 'reminders' => 0], $second);
        $this->assertSame('expired', $ended->fresh()->status);
        $this->assertSame('active', $endsToday->fresh()->status, 'The contract end date is inclusive.');
        $this->assertDatabaseHas('notifications', [
            'notification_key' => "contract.expired.{$ended->id}.2026-09-07",
        ]);
        $this->assertDatabaseHas('notifications', [
            'notification_key' => "contract.expiry.{$dueSoon->id}.2026-09-22.14",
        ]);
        $this->assertDatabaseHas('notifications', [
            'notification_key' => "contract.expiry.{$endsToday->id}.2026-09-08.0",
        ]);
        $this->assertDatabaseCount('notifications', 3);
        $this->assertSame(3, $manager->notifications()->count());
    }

    public function test_contract_can_continue_as_a_new_reviewable_draft_without_mutating_the_source(): void
    {
        Carbon::setTestNow('2026-09-08 09:00:00');
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('name', 'super_admin')->firstOrFail());
        Sanctum::actingAs($user, ['access']);

        $type = ContractType::query()->where('slug', 'customer')->firstOrFail();
        $template = ContractTemplate::factory()->create([
            'contract_type_id' => $type->id,
            'content' => '<p>Renewable customer agreement</p>',
        ]);
        $customer = Customer::factory()->create();
        $source = Contract::factory()->create([
            'template_id' => $template->id,
            'contract_type_id' => $type->id,
            'customer_id' => $customer->id,
            'status' => 'expired',
            'start_date' => '2025-08-31',
            'end_date' => '2026-08-31',
            'duration_months' => 12,
            'counterparty' => $customer->name,
        ]);

        $response = $this->postJson("/api/contracts/{$source->id}/renew", [
            'duration_months' => 6,
        ])->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.start_date', '2026-09-08')
            ->assertJsonPath('data.end_date', '2027-03-08')
            ->assertJsonPath('data.duration_months', 6)
            ->assertJsonPath('data.snapshot_data.renewal.source_contract_id', $source->id);

        $renewal = Contract::query()->findOrFail($response->json('data.id'));
        $this->assertNotSame($source->id, $renewal->id);
        $this->assertNotSame($source->contract_number, $renewal->contract_number);
        $this->assertSame('expired', $source->fresh()->status);
        $this->assertSame('2026-08-31', $source->fresh()->end_date->toDateString());

        $this->postJson("/api/contracts/{$source->id}/renew", ['duration_months' => 6])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'business.contract_renewal_exists');
    }

    public function test_cancelled_and_draft_contracts_cannot_be_renewed(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('name', 'super_admin')->firstOrFail());
        Sanctum::actingAs($user, ['access']);

        foreach (['draft', 'cancelled'] as $status) {
            $contract = Contract::factory()->create([
                'status' => $status,
                'is_cancelled' => $status === 'cancelled',
                'end_date' => '2026-12-01',
            ]);

            $this->postJson("/api/contracts/{$contract->id}/renew", ['duration_months' => 12])
                ->assertUnprocessable()
                ->assertJsonPath('code', 'business.contract_not_renewable');
        }
    }
}
