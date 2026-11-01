<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Currency;
use App\Models\Income;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * An issued receipt is not silently revalued: a value change needs a reason,
 * the reason is persisted, and the change is audited with old and new values.
 */
class IncomeRevaluationTest extends TestCase
{
    use RefreshDatabase;

    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
        $this->currency = Currency::factory()->create(['code' => 'AFN', 'is_base' => true, 'rate' => 1]);
    }

    private function receipt(array $overrides = []): array
    {
        return $this->postJson('/api/incomes', [
            'type' => 'Other',
            'description' => 'Standalone receipt',
            'amount' => 100,
            'quantity' => 2,
            'currency_id' => $this->currency->id,
            'date' => now()->toDateString(),
            ...$overrides,
        ])->assertCreated()->json('data');
    }

    public function test_a_value_change_without_a_reason_is_rejected_and_the_receipt_is_unchanged(): void
    {
        $receipt = $this->receipt();

        foreach ([['amount' => 150], ['quantity' => 3], ['rate' => 2]] as $change) {
            $this->putJson('/api/incomes/'.$receipt['id'], [...$change, 'revaluation_reason' => '   '])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('revaluation_reason');
        }

        $stored = Income::findOrFail($receipt['id']);
        $this->assertSame(100.0, (float) $stored->amount);
        $this->assertSame(2, $stored->quantity);
        $this->assertNull($stored->revaluation_reason);
    }

    public function test_a_reasoned_value_change_is_persisted_and_audited_with_old_and_new_values(): void
    {
        $receipt = $this->receipt(['amount' => 6000, 'quantity' => 3]);

        $updated = $this->putJson('/api/incomes/'.$receipt['id'], [
            'amount' => 5000,
            'revaluation_reason' => 'Discount approved by the finance manager',
        ])->assertOk()
            ->assertJsonPath('data.amount', 5000)
            ->assertJsonPath('data.revaluation_reason', 'Discount approved by the finance manager')
            ->json('data');

        $this->assertSame($receipt['receipt_number'], $updated['receipt_number']);

        $audit = AuditLog::query()
            ->where('table_name', 'incomes')
            ->where('auditable_id', $receipt['id'])
            ->where('event', 'updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertContains('revaluation_reason', $audit->changed_columns);
        $this->assertSame('Discount approved by the finance manager', $audit->new_values['revaluation_reason']);
        $this->assertSame(6000.0, (float) $audit->old_values['amount']);
        $this->assertSame(5000.0, (float) $audit->new_values['amount']);
    }

    public function test_a_description_or_date_edit_needs_no_reason_and_keeps_the_receipt_number(): void
    {
        $receipt = $this->receipt();

        $this->putJson('/api/incomes/'.$receipt['id'], [
            'description' => 'Updated description',
            'date' => now()->subDay()->toDateString(),
        ])->assertOk()
            ->assertJsonPath('data.description', 'Updated description')
            ->assertJsonPath('data.receipt_number', $receipt['receipt_number'])
            ->assertJsonPath('data.revaluation_reason', null);

        $this->assertDatabaseHas('incomes', [
            'id' => $receipt['id'],
            'description' => 'Updated description',
            'revaluation_reason' => null,
        ]);
    }
}
