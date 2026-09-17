<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PendingTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_income_posts_once(): void
    {
        $role = Role::firstOrCreate(['name' => 'super_admin']);
        $creator = User::factory()->create(); $creator->roles()->attach($role);
        Sanctum::actingAs($creator, ['access']);
        $currency = Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $pending = $this->postJson('/api/pending-transactions', ['transaction_type' => 'income', 'reason' => 'Deposit', 'amount' => 20, 'currency_id' => $currency->id])->assertCreated()->json('data');
        $this->postJson("/api/pending-transactions/{$pending['id']}/post")->assertUnprocessable();
        $this->postJson("/api/pending-transactions/{$pending['id']}/approve")->assertUnprocessable();
        $approver = User::factory()->create(); $approver->roles()->attach($role);
        Sanctum::actingAs($approver, ['access']);
        $this->postJson("/api/pending-transactions/{$pending['id']}/approve")->assertOk();
        $this->postJson("/api/pending-transactions/{$pending['id']}/post")->assertOk();
        $this->postJson("/api/pending-transactions/{$pending['id']}/post")->assertOk();
        $this->assertDatabaseCount('incomes', 1);
        $this->assertDatabaseHas('incomes', ['pending_transaction_id' => $pending['id']]);
    }
}
