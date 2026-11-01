<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorthyEmployee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BonusGrantTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $actor = User::factory()->create();
        $actor->syncRoles(['super_admin']);
        Sanctum::actingAs($actor, ['access']);

        $this->currency = Currency::factory()->create(['is_base' => true]);
        $this->employee = Employee::factory()->create();
    }

    public function test_zero_stored_amount_cannot_be_granted(): void
    {
        $bonus = $this->nomination(0);

        $this->patchJson("/api/worthy-employees/{$bonus->id}", ['isGranted' => true])
            ->assertStatus(422)
            ->assertJsonPath('errors.grantedAmount.0', 'business.bonus_amount_required');

        $this->assertFalse($bonus->fresh()->is_granted);
        $this->assertNull($bonus->fresh()->granted_amount);
    }

    public function test_explicit_zero_grant_is_rejected_without_changing_the_nomination(): void
    {
        $bonus = $this->nomination(1000);

        $this->patchJson("/api/worthy-employees/{$bonus->id}", [
            'isGranted' => true,
            'grantedAmount' => 0,
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.grantedAmount.0', 'business.bonus_amount_required');

        $this->assertFalse($bonus->fresh()->is_granted);
        $this->assertSame(1000.0, $bonus->fresh()->selected_amount);
        $this->assertNull($bonus->fresh()->granted_amount);
    }

    public function test_negative_grant_is_rejected_by_validation(): void
    {
        $bonus = $this->nomination(1000);

        $this->patchJson("/api/worthy-employees/{$bonus->id}", [
            'isGranted' => true,
            'grantedAmount' => -1,
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.grantedAmount.0', 'validation.min');

        $this->assertFalse($bonus->fresh()->is_granted);
    }

    public function test_explicit_amount_is_the_amount_shown_and_persisted(): void
    {
        $bonus = $this->nomination(1000);

        $this->patchJson("/api/worthy-employees/{$bonus->id}", [
            'isGranted' => true,
            'grantedAmount' => 1250.5,
        ])
            ->assertOk()
            ->assertJsonPath('data.isGranted', true)
            ->assertJsonPath('data.grantedAmount', 1250.5)
            ->assertJsonPath('data.selectedAmount', 1250.5);

        $fresh = $bonus->fresh();
        $this->assertTrue($fresh->is_granted);
        $this->assertSame(1250.5, $fresh->granted_amount);
        $this->assertSame(1250.5, $fresh->selected_amount);
    }

    public function test_a_granted_bonus_cannot_be_granted_again(): void
    {
        $bonus = $this->nomination(1000, granted: true);

        $this->patchJson("/api/worthy-employees/{$bonus->id}", [
            'isGranted' => true,
            'grantedAmount' => 2000,
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'business.granted_bonus_immutable');

        $fresh = $bonus->fresh();
        $this->assertSame(1000.0, $fresh->granted_amount);
        $this->assertSame(1000.0, $fresh->selected_amount);
    }

    public function test_granted_amount_is_available_to_the_payroll_query(): void
    {
        $bonus = $this->nomination(1000);

        $this->patchJson("/api/worthy-employees/{$bonus->id}", [
            'isGranted' => true,
            'grantedAmount' => 1000,
        ])->assertOk();

        $this->getJson("/api/salaries/granted-bonuses?employee_id={$this->employee->id}&salaryMonth=2026-10")
            ->assertOk()
            ->assertJsonPath('data.0.id', (string) $bonus->id)
            ->assertJsonPath('data.0.grantedAmount', 1000);
    }

    private function nomination(float $amount, bool $granted = false): WorthyEmployee
    {
        return WorthyEmployee::create([
            'employee_id' => $this->employee->id,
            'achievement_details' => 'Outstanding work',
            'suggested_amount' => $amount,
            'selected_amount' => $amount,
            'selected_type' => 'Performance',
            'is_granted' => $granted,
            'granted_amount' => $granted ? $amount : null,
            'bonus_month' => '2026-10-01',
            'currency_id' => $this->currency->id,
            'base_currency_id' => $this->currency->id,
            'currency_code' => $this->currency->code,
            'currency_name' => $this->currency->name,
            'currency_symbol' => $this->currency->symbol,
            'currency_decimal_places' => $this->currency->decimal_places,
            'base_currency_code' => $this->currency->code,
            'rate' => 1,
            'base_amount' => $amount,
            'currency_snapshot_source' => 'recorded_at_creation',
        ]);
    }
}
