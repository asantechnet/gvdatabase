<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Currency;
use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssetValuationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_asset_stores_currency_and_acquisition_date_and_returns_total_value(): void
    {
        $currency = $this->baseCurrency();
        $office = Office::factory()->create();

        $this->postJson('/api/assets', $this->payload($currency, $office))
            ->assertCreated()
            ->assertJsonPath('data.currency_id', $currency->id)
            ->assertJsonPath('data.currency.code', 'AFN')
            ->assertJsonPath('data.acquisition_date', '2026-10-01')
            ->assertJsonPath('data.total_value', 450)
            ->assertJsonPath('data.book_value', null);

        $this->assertDatabaseHas('assets', [
            'serial_number' => 'VAL-1001',
            'currency_id' => $currency->id,
            'acquisition_date' => '2026-10-01',
            'quantity' => 5,
            'price' => 90,
        ]);
    }

    public function test_book_value_uses_straight_line_and_never_falls_below_salvage_value(): void
    {
        Carbon::setTestNow('2026-10-31 12:00:00');

        $currency = $this->baseCurrency();
        $office = Office::factory()->create();
        $payload = $this->payload($currency, $office, [
            'acquisition_date' => '2024-10-31',
            'useful_life_years' => 4,
            'salvage_value' => 50,
        ]);

        $id = $this->postJson('/api/assets', $payload)
            ->assertCreated()
            ->assertJsonPath('data.total_value', 450)
            ->assertJsonPath('data.book_value', 250)
            ->json('data.id');

        $this->putJson('/api/assets/'.$id, ['acquisition_date' => '2010-01-01'])
            ->assertOk()
            ->assertJsonPath('data.total_value', 450)
            ->assertJsonPath('data.book_value', 50);
    }

    public function test_asset_valuation_validation_rejects_missing_currency_negative_salvage_and_future_date(): void
    {
        $currency = $this->baseCurrency();
        $office = Office::factory()->create();
        $payload = $this->payload($currency, $office);

        $missingCurrency = $payload;
        unset($missingCurrency['currency_id']);
        $this->postJson('/api/assets', $missingCurrency)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('currency_id');

        $this->postJson('/api/assets', [...$payload, 'salvage_value' => -1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('salvage_value');

        $this->postJson('/api/assets', [...$payload, 'acquisition_date' => '2099-01-01'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('acquisition_date');
    }

    public function test_legacy_valuation_fields_survive_an_unchanged_edit(): void
    {
        $currency = $this->baseCurrency();
        $office = Office::factory()->create();

        $id = $this->postJson('/api/assets', $this->payload($currency, $office))
            ->assertCreated()
            ->json('data.id');

        $asset = $this->getJson('/api/assets/'.$id)->assertOk()->json('data');
        unset($asset['id'], $asset['office'], $asset['currency'], $asset['allocations'], $asset['allocated_quantity'], $asset['available_quantity'], $asset['total_value'], $asset['book_value'], $asset['created_at'], $asset['updated_at']);

        $this->putJson('/api/assets/'.$id, $asset)
            ->assertOk()
            ->assertJsonPath('data.currency_id', $currency->id)
            ->assertJsonPath('data.acquisition_date', '2026-10-01');
    }

    public function test_assets_table_has_the_valuation_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('assets', [
            'currency_id',
            'acquisition_date',
            'useful_life_years',
            'salvage_value',
        ]));
    }

    private function baseCurrency(): Currency
    {
        return Currency::factory()->create([
            'code' => 'AFN',
            'name' => 'Afghan Afghani',
            'is_base' => true,
            'is_active' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(Currency $currency, Office $office, array $overrides = []): array
    {
        return array_merge([
            'serial_number' => 'VAL-1001',
            'item' => 'Laptop',
            'quantity' => 5,
            'price' => 90,
            'currency_id' => $currency->id,
            'acquisition_date' => '2026-10-01',
            'useful_life_years' => null,
            'salvage_value' => 0,
            'model' => 'ThinkPad',
            'condition' => 'good',
            'office_id' => $office->id,
            'allocations' => [],
        ], $overrides);
    }
}
