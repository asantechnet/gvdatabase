<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CurrencyVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_ordinary_pickers_receive_only_active_currencies(): void
    {
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
        Currency::factory()->create(['code' => 'USD', 'is_active' => true, 'is_base' => true]);
        Currency::factory()->create(['code' => 'AFN', 'is_active' => false]);

        $this->getJson('/api/currencies')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'USD');

        $this->getJson('/api/currencies?include_inactive=1')->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_inactive_currencies_require_currency_management_access(): void
    {
        $role = Role::create(['name' => 'income_viewer']);
        $role->permissions()->attach(Permission::firstOrCreate(
            ['name' => 'view_incomes'],
            ['category' => 'finance', 'guard_name' => 'api'],
        ));
        $user = User::factory()->create(['roles' => ['income_viewer']]);
        Sanctum::actingAs($user, ['access']);

        Currency::factory()->create(['code' => 'USD', 'is_active' => true, 'is_base' => true]);
        Currency::factory()->create(['code' => 'AFN', 'is_active' => false]);

        $this->getJson('/api/currencies?include_inactive=1')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'USD');
    }
}
