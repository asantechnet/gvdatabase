<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\AuditLog;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExpenseSimplificationTest extends TestCase
{
    use RefreshDatabase;

    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
        Currency::factory()->create(['code' => 'USD', 'rate' => 1, 'is_base' => true]);
        $this->currency = Currency::factory()->create(['code' => 'AFN', 'rate' => 70, 'is_base' => false]);
    }

    private function payload(array $overrides = []): array
    {
        return [...[
            'type' => 'Utilities', 'description' => 'Power bill',
            'amount' => 130, 'quantity' => 1, 'currency_id' => $this->currency->id,
            'date' => now()->toDateString(),
        ], ...$overrides];
    }

    public function test_applicant_is_optional_and_can_be_assigned_preserved_and_cleared(): void
    {
        $applicant = Applicant::factory()->create();
        $id = $this->postJson('/api/expenses', $this->payload())
            ->assertCreated()->assertJsonPath('data.applicant_id', null)
            ->assertJsonPath('data.applicant', null)->json('data.id');

        $this->putJson("/api/expenses/{$id}", ['applicant_id' => $applicant->id])
            ->assertOk()->assertJsonPath('data.applicant.name', $applicant->name);
        $this->putJson("/api/expenses/{$id}", ['description' => 'Updated bill'])
            ->assertOk()->assertJsonPath('data.applicant_id', $applicant->id);
        $this->getJson('/api/expenses')->assertOk()->assertJsonPath('data.0.applicant.id', $applicant->id);
        $this->putJson("/api/expenses/{$id}", ['applicant_id' => null])
            ->assertOk()->assertJsonPath('data.applicant', null);

        $this->assertFalse(Schema::hasColumn('expenses', 'company_id'));
    }

    public function test_missing_or_archived_applicants_are_rejected(): void
    {
        $applicant = Applicant::factory()->create();
        $applicant->delete();
        foreach ([$applicant->id, $applicant->id + 9999] as $id) {
            $this->postJson('/api/expenses', $this->payload(['applicant_id' => $id]))
                ->assertUnprocessable()->assertJsonValidationErrors('applicant_id');
        }
        $expenseId = $this->postJson('/api/expenses', $this->payload())->assertCreated()->json('data.id');
        $this->putJson("/api/expenses/{$expenseId}", ['applicant_id' => $applicant->id])
            ->assertUnprocessable()->assertJsonValidationErrors('applicant_id');
    }

    public function test_supplied_rates_are_remembered_and_audited_without_rewriting_old_expenses(): void
    {
        $first = $this->postJson('/api/expenses', $this->payload(['rate' => 65]))
            ->assertCreated()->assertJsonPath('data.rate', 65)->assertJsonPath('data.base_amount', 2)
            ->json('data.id');
        $this->assertSame(65.0, $this->currency->fresh()->rate);
        $this->assertTrue(AuditLog::where('table_name', 'currencies')
            ->where('auditable_id', $this->currency->id)->where('event', 'updated')->exists());

        $second = $this->postJson('/api/expenses', $this->payload())
            ->assertCreated()->assertJsonPath('data.rate', 65)->json('data.id');
        $this->putJson("/api/expenses/{$second}", ['rate' => 52])
            ->assertOk()->assertJsonPath('data.rate', 52)->assertJsonPath('data.base_amount', 2.5);
        $this->assertSame(52.0, $this->currency->fresh()->rate);
        $this->assertSame(65.0, (float) Expense::findOrFail($first)->rate);
        $this->putJson("/api/expenses/{$first}", ['description' => 'Historical expense'])
            ->assertOk()->assertJsonPath('data.rate', 65);
        $this->assertSame(52.0, $this->currency->fresh()->rate);
    }

    public function test_currency_changes_snapshot_the_new_currency_and_null_rate_preserves_history(): void
    {
        $id = $this->postJson('/api/expenses', $this->payload(['rate' => 65]))->assertCreated()->json('data.id');
        $other = Currency::factory()->create(['code' => 'EUR', 'rate' => 2, 'is_base' => false]);
        $this->putJson("/api/expenses/{$id}", ['currency_id' => $other->id, 'rate' => 4])
            ->assertOk()->assertJsonPath('data.rate', 4)->assertJsonPath('data.cnv_curr_shrt', 'EUR')
            ->assertJsonPath('data.base_amount', 32.5);
        $this->assertSame(4.0, $other->fresh()->rate);
        $this->putJson("/api/expenses/{$id}", ['rate' => null])
            ->assertOk()->assertJsonPath('data.rate', 4);
        $this->putJson("/api/expenses/{$id}", ['currency_id' => $this->currency->id])
            ->assertOk()->assertJsonPath('data.rate', 65)->assertJsonPath('data.base_amount', 2);
    }

    public function test_invalid_rates_do_not_change_the_currency_or_create_an_expense(): void
    {
        foreach ([0, -1, 0.000000001] as $rate) {
            $this->postJson('/api/expenses', $this->payload(['rate' => $rate]))
                ->assertUnprocessable()->assertJsonValidationErrors('rate');
        }
        $this->assertSame(70.0, $this->currency->fresh()->rate);
        $this->assertSame(0, Expense::count());
    }

    public function test_a_failed_expense_save_rolls_back_the_rate_and_audit(): void
    {
        $before = AuditLog::count();
        Expense::creating(function () {
            throw new \RuntimeException('Expense write failed');
        });
        try {
            $this->postJson('/api/expenses', $this->payload(['rate' => 65]))->assertStatus(500);
            $this->assertSame(70.0, $this->currency->fresh()->rate);
            $this->assertSame(0, Expense::count());
            $this->assertSame($before, AuditLog::count());
        } finally {
            Expense::flushEventListeners();
            Expense::clearBootedModels();
        }
    }

    public function test_a_failed_expense_update_rolls_back_the_rate_and_audit(): void
    {
        $id = $this->postJson('/api/expenses', $this->payload())->assertCreated()->json('data.id');
        $before = AuditLog::count();
        Expense::updating(function () {
            throw new \RuntimeException('Expense update failed');
        });
        try {
            $this->putJson("/api/expenses/{$id}", ['rate' => 65])->assertStatus(500);
            $this->assertSame(70.0, $this->currency->fresh()->rate);
            $this->assertSame(70.0, (float) Expense::findOrFail($id)->rate);
            $this->assertSame($before, AuditLog::count());
        } finally {
            Expense::flushEventListeners();
            Expense::clearBootedModels();
        }
    }
}
