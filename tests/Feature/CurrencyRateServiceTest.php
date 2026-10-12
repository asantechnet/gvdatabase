<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Currency;
use App\Models\CurrencyRateHistory;
use App\Models\User;
use App\Services\CurrencyRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Setting a rate anywhere in the product must also update the currency, so the
 * next screen pre-fills the same value.
 */
class CurrencyRateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_supplied_rate_is_persisted_on_the_currency(): void
    {
        $currency = Currency::factory()->create(['rate' => 1]);
        $service = app(CurrencyRateService::class);

        $this->assertSame(72.5, $service->remember($currency->id, '72.5'));
        $this->assertSame(72.5, (float) $currency->fresh()->rate);
    }

    public function test_an_unusable_rate_leaves_the_stored_value_untouched(): void
    {
        $currency = Currency::factory()->create(['rate' => 3]);
        $service = app(CurrencyRateService::class);

        $this->assertNull($service->remember($currency->id, 0));
        $this->assertNull($service->remember($currency->id, 'not-a-number'));
        $this->assertNull($service->remember($currency->id, null));
        $this->assertNull($service->remember($currency->id, INF));
        $this->assertNull($service->remember($currency->id, NAN));
        $this->assertNull($service->remember($currency->id, 0.000000001));
        $this->assertNull($service->remember($currency->id + 9999, 5));

        $this->assertSame(3.0, (float) $currency->fresh()->rate);
    }

    public function test_base_currency_always_returns_and_preserves_unit_rate(): void
    {
        $currency = Currency::factory()->create(['is_base' => true]);
        $this->assertSame(1.0, app(CurrencyRateService::class)->remember($currency->id, 70));
        $this->assertSame(1.0, (float) $currency->fresh()->rate);
    }

    public function test_rate_and_audit_roll_back_with_the_callers_transaction(): void
    {
        $currency = Currency::factory()->create(['rate' => 3]);
        $before = AuditLog::count();
        DB::beginTransaction();
        app(CurrencyRateService::class)->remember($currency->id, 7);
        $this->assertSame(7.0, (float) $currency->fresh()->rate);
        DB::rollBack();
        $this->assertSame(3.0, (float) $currency->fresh()->rate);
        $this->assertSame($before, AuditLog::count());
    }

    public function test_income_and_expense_snapshots_cannot_override_the_base_rate(): void
    {
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
        $base = Currency::factory()->create(['is_base' => true]);
        // Each portal validates its type against `finance_types`, whose defaults
        // differ per kind ('Service' for income, 'Salaries' for expense).
        foreach ([['incomes', 'Service'], ['expenses', 'Salaries']] as [$portal, $type]) {
            $id = $this->postJson("/api/{$portal}", [
                'type' => $type, 'description' => 'Base currency payment',
                'amount' => 100, 'quantity' => 1, 'currency_id' => $base->id,
                'rate' => 70, 'date' => now()->toDateString(),
            ])->assertCreated()->assertJsonPath('data.rate', 1)
                ->assertJsonPath('data.base_amount', 100)->json('data.id');
            $this->putJson("/api/{$portal}/{$id}", ['rate' => 80])
                ->assertOk()->assertJsonPath('data.rate', 1)
                ->assertJsonPath('data.base_amount', 100);
        }
        $this->assertSame(1.0, (float) $base->fresh()->rate);
    }

    public function test_currency_settings_share_the_rate_writer_and_preserve_rebasing(): void
    {
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
        $base = Currency::factory()->create(['code' => 'USD', 'is_base' => true]);
        $id = $this->postJson('/api/currencies', [
            'name' => 'Afghani', 'code' => 'AFN', 'symbol' => 'AFN', 'rate' => 70,
        ])->assertCreated()->assertJsonPath('data.rate', 70)->json('data.id');

        $this->putJson("/api/currencies/{$id}", ['rate' => 72.123456789])
            ->assertOk()->assertJsonPath('data.rate', 72.12345679);
        $this->assertTrue(CurrencyRateHistory::where('currency_id', $id)
            ->where('reason', 'rate_updated')->exists());
        $this->putJson("/api/currencies/{$base->id}", ['rate' => 5])
            ->assertOk()->assertJsonPath('data.rate', 1);
        $this->putJson("/api/currencies/{$id}", ['rate' => 0.000000001])
            ->assertUnprocessable()->assertJsonValidationErrors('rate');
        $this->putJson("/api/currencies/{$id}", ['is_base' => true, 'rate' => 50])
            ->assertOk()->assertJsonPath('data.rate', 1);
        $this->assertSame(0.02, (float) $base->fresh()->rate);
        $this->assertTrue(Currency::findOrFail($id)->is_base);
    }
}
