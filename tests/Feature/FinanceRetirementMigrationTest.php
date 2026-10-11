<?php

namespace Tests\Feature;

use App\Models\ApplicantContract;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Income;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesBuiltInContracts;
use Tests\TestCase;

class FinanceRetirementMigrationTest extends TestCase
{
    use CreatesBuiltInContracts;
    use DatabaseTruncation;

    private const RETIRED_TABLES = [
        'companies', 'invoices', 'invoice_service', 'payment_details',
        'invoice_service_commission_recipients', 'applicant_contract_employee',
        'employee_service_commissions', 'commission_accruals', 'commission_settlements',
        'outsourcing_accruals', 'outsourcing_settlements',
    ];

    protected function setUp(): void
    {
        RefreshDatabaseState::$migrated = false;
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        RefreshDatabaseState::$migrated = false;
    }

    private function assertRetiredSchema(): void
    {
        foreach (self::RETIRED_TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table), $table);
        }
        foreach (['contracts', 'incomes', 'expenses'] as $table) {
            $this->assertFalse(Schema::hasColumn($table, 'company_id'));
        }
        foreach (['invoice_id', 'invoice_amount'] as $column) {
            $this->assertFalse(Schema::hasColumn('incomes', $column));
        }
        foreach (['outsourcing_company_id', 'outsourcing_percentage', 'marketer_percentage'] as $column) {
            $this->assertFalse(Schema::hasColumn('applicant_contract', $column));
        }
        $this->assertFalse(Schema::hasColumn('employees', 'employment_type'));
        $this->assertFalse(Schema::hasColumn('visitors', 'company'));
        $this->assertFalse(Schema::hasColumn('settings', 'invoice_letterhead'));
    }

    public function test_fresh_install_and_retirement_down_methods_never_create_retired_entities(): void
    {
        $this->assertRetiredSchema();
        foreach (['000003_drop_commission_and_outsourcing', '000005_drop_invoicing', '000007_drop_companies'] as $name) {
            $migration = require database_path('migrations/2026_10_08_'.$name.'.php');
            $migration->down();
            $migration->up();
        }
        $this->assertRetiredSchema();
    }

    public function test_populated_retirement_discards_removed_concepts_without_breaking_current_payments(): void
    {
        $currency = Currency::factory()->create(['is_base' => true]);
        $data = $this->createBuiltInContract('academy', ['currency_id' => $currency->id]);
        $contract = Contract::findOrFail($data['id']);
        $line = ApplicantContract::where('contract_id', $contract->id)->firstOrFail();
        $id = $this->postJson('/api/incomes', [
            'applicant_contract_id' => $line->id, 'type' => 'Service', 'amount' => 25,
            'quantity' => 1, 'currency_id' => $currency->id, 'date' => now()->toDateString(),
        ])->assertCreated()->json('data.id');
        $receipt = Income::findOrFail($id)->receipt_number;
        $document = $contract->rendered_content;
        $tasks = DB::table('tasks')->count();

        // Reconstruct just enough of an existing installation to exercise
        // actual foreign keys, indexes and populated linked payment cleanup.
        foreach (self::RETIRED_TABLES as $table) {
            Schema::create($table, function (Blueprint $blueprint): void {
                $blueprint->id();
            });
            DB::table($table)->insert(['id' => 1]);
        }
        Schema::table('incomes', function (Blueprint $table): void {
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->decimal('invoice_amount', 20, 6)->nullable();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->index('invoice_id', 'incomes_invoice_id_index');
            $table->index('company_id', 'income_company_idx');
        });
        Schema::table('contracts', function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
        });
        Schema::table('applicant_contract', function (Blueprint $table): void {
            $table->foreignId('outsourcing_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->decimal('outsourcing_percentage', 5, 2)->nullable();
            $table->decimal('marketer_percentage', 5, 2)->nullable();
        });
        Schema::table('employees', fn (Blueprint $table) => $table->string('employment_type')->default('salaried'));
        Schema::table('visitors', fn (Blueprint $table) => $table->string('company')->default(''));
        Schema::table('settings', function (Blueprint $table): void {
            foreach (['', '_source', '_safe_x', '_safe_y', '_safe_width', '_safe_height'] as $suffix) {
                $table->string('invoice_letterhead'.$suffix)->nullable();
            }
        });
        DB::table('incomes')->where('id', $id)->update(['invoice_id' => 1, 'invoice_amount' => 25, 'company_id' => 1]);
        DB::table('contracts')->where('id', $contract->id)->update(['company_id' => 1]);
        DB::table('applicant_contract')->where('id', $line->id)->update(['outsourcing_company_id' => 1, 'outsourcing_percentage' => 30, 'marketer_percentage' => 10]);

        foreach (['000003_drop_commission_and_outsourcing', '000005_drop_invoicing', '000007_drop_companies'] as $name) {
            $migration = require database_path('migrations/2026_10_08_'.$name.'.php');
            $migration->up();
            $migration->up();
            $migration->down();
        }

        $this->assertRetiredSchema();
        $this->assertSame($receipt, Income::findOrFail($id)->receipt_number);
        $this->assertSame($document, $contract->fresh()->rendered_content);
        $this->assertSame($tasks, DB::table('tasks')->count());
        $this->assertSame(25.0, (float) $line->fresh()->amount_paid);
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        $this->getJson('/api/incomes/'.$id.'/receipt')->assertOk()->assertJsonMissingPath('data.invoice');
    }
}
