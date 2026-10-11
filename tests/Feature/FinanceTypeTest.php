<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\FinanceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinanceTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
    }

    public function test_catalogue_crud_is_scoped_by_kind_and_keeps_values_stable(): void
    {
        $id = $this->postJson('/api/finance-types/income', ['name' => 'Consulting'])
            ->assertCreated()->assertJsonPath('data.value', 'Consulting')->assertJsonPath('data.is_system', false)->json('data.id');
        $this->postJson('/api/finance-types/income', ['name' => 'Consulting'])->assertUnprocessable();
        $this->postJson('/api/finance-types/income', ['name' => ['invalid']])->assertUnprocessable();
        $this->postJson('/api/finance-types/income', ['name' => '   '])->assertUnprocessable();
        $this->postJson('/api/finance-types/expense', ['name' => 'Consulting'])->assertCreated();
        $this->putJson("/api/finance-types/expense/{$id}", ['name' => 'Wrong kind'])->assertNotFound();
        $this->putJson("/api/finance-types/income/{$id}", ['name' => 'Advisory', 'value' => 'Changed'])
            ->assertOk()->assertJsonPath('data.name', 'Advisory')->assertJsonPath('data.value', 'Consulting');
        $this->getJson("/api/finance-types/income/{$id}")->assertOk()->assertJsonPath('data.name', 'Advisory');
        $this->getJson('/api/finance-types/income')->assertOk()->assertJsonFragment(['name' => 'Advisory']);
        $this->deleteJson("/api/finance-types/income/{$id}")->assertNoContent();
        $this->getJson('/api/finance-types/unknown')->assertNotFound();
    }

    public function test_custom_types_are_accepted_by_transactions_and_used_types_cannot_be_deleted(): void
    {
        $currency = Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $data = ['currency_id' => $currency->id, 'amount' => 50, 'quantity' => 1, 'rate' => 1, 'date' => now()->toDateString(), 'description' => 'Example'];
        foreach (['income' => 'incomes', 'expense' => 'expenses'] as $kind => $endpoint) {
            $type = FinanceType::factory()->create(['kind' => $kind]);
            $this->postJson("/api/{$endpoint}", [...$data, 'type' => $type->value])->assertCreated();
            $this->postJson("/api/{$endpoint}", [...$data, 'type' => 'Unregistered'])->assertUnprocessable();
            $this->postJson("/api/{$endpoint}", [...$data, 'type' => [$type->value]])->assertUnprocessable();
            $this->deleteJson("/api/finance-types/{$kind}/{$type->id}")->assertUnprocessable()->assertJsonPath('errors.name.0', 'business.finance_type_in_use');
            $this->putJson("/api/finance-types/{$kind}/{$type->id}", ['name' => 'Renamed'])->assertOk();
            $this->assertDatabaseHas($endpoint, ['type' => $type->value]);
        }
        $service = FinanceType::where('kind', 'income')->where('value', 'Service')->firstOrFail();
        $this->deleteJson("/api/finance-types/income/{$service->id}")->assertUnprocessable();
    }

    public function test_users_without_settings_access_cannot_manage_types(): void
    {
        Sanctum::actingAs(User::factory()->create(['roles' => []]), ['access']);
        $this->postJson('/api/finance-types/income', ['name' => 'Unauthorized'])->assertForbidden();
    }
}
