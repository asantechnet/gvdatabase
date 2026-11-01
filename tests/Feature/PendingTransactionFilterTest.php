<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Income;
use App\Models\PendingTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PendingTransactionFilterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create(['roles' => ['super_admin']]);
        Sanctum::actingAs($admin, ['access']);

        return $admin;
    }

    private function currency(): Currency
    {
        return Currency::factory()->create([
            'is_base' => true,
            'is_active' => true,
            'rate' => 1,
            'decimal_places' => 2,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function request(User $requester, Currency $currency, array $overrides = []): PendingTransaction
    {
        return PendingTransaction::create(array_merge([
            'transaction_type' => 'income',
            'reason' => 'Client payment',
            'amount' => 500,
            'currency_id' => $currency->id,
            'requested_by' => $requester->id,
            'approval_status' => PendingTransaction::SUBMITTED,
            'requested_at' => now(),
        ], $overrides));
    }

    public function test_search_status_and_type_filters_narrow_the_server_page(): void
    {
        $admin = $this->admin();
        $currency = $this->currency();
        $this->request($admin, $currency, ['reason' => 'Office rent', 'transaction_type' => 'expense']);
        $this->request($admin, $currency, ['reason' => 'Tuition payment', 'transaction_type' => 'income']);

        $this->getJson('/api/payment-requests?search=rent&status=submitted&transaction_type=expense')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.reason', 'Office rent')
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.current_page', 1);

        $this->getJson('/api/payment-requests?status=approved')
            ->assertOk()
            ->assertJsonCount(0, 'data.data')
            ->assertJsonPath('data.total', 0);
    }

    public function test_unknown_filter_values_are_validation_errors(): void
    {
        $this->admin();

        $this->getJson('/api/payment-requests?status=not-a-status')
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed');

        $this->getJson('/api/payment-requests?transaction_type=not-a-type')
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed');
    }

    public function test_a_requester_cannot_approve_or_post_their_own_request(): void
    {
        $admin = $this->admin();
        $currency = $this->currency();
        $submitted = $this->request($admin, $currency);
        $approved = $this->request($admin, $currency, ['approval_status' => PendingTransaction::APPROVED]);

        $this->postJson("/api/payment-requests/{$submitted->id}/approve")
            ->assertStatus(422)
            ->assertJsonPath('code', 'business.pending_transaction_self_approval');

        $this->postJson("/api/payment-requests/{$approved->id}/post")
            ->assertStatus(422)
            ->assertJsonPath('code', 'business.pending_transaction_self_approval');

        $this->assertSame(PendingTransaction::SUBMITTED, $submitted->fresh()->approval_status);
        $this->assertSame(PendingTransaction::APPROVED, $approved->fresh()->approval_status);
        $this->assertSame(0, Income::query()->count());
    }

    public function test_another_approver_can_post_an_approved_request(): void
    {
        $admin = $this->admin();
        $currency = $this->currency();
        $requester = User::factory()->create();
        $pending = $this->request($requester, $currency, ['approval_status' => PendingTransaction::APPROVED]);

        $this->postJson("/api/payment-requests/{$pending->id}/post")
            ->assertOk()
            ->assertJsonPath('data.approval_status', PendingTransaction::POSTED);

        $this->assertDatabaseHas('incomes', ['pending_transaction_id' => $pending->id]);
    }
}
