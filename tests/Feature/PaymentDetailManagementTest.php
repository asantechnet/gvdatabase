<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Applicant;
use App\Models\Contract;
use App\Models\ContractService;
use App\Models\ContractType;
use App\Models\Invoice;
use App\Models\PaymentDetail;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentDetailManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
    }

    public function test_payment_details_are_crudable(): void
    {
        $created = $this->postJson('/api/payment-details', [
            'method' => 'Bank transfer',
            'instruction' => "Account: 12345\nReference the invoice number.",
        ])->assertCreated()
            ->assertJsonPath('data.method', 'Bank transfer');

        $paymentDetailId = $created->json('data.id');

        $this->getJson('/api/payment-details')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->putJson('/api/payment-details/'.$paymentDetailId, [
            'method' => 'Wire transfer',
            'instruction' => 'Use the SWIFT details on your contract.',
        ])->assertOk()
            ->assertJsonPath('data.method', 'Wire transfer');

        $this->deleteJson('/api/payment-details/'.$paymentDetailId)->assertOk();

        $this->assertSoftDeleted('payment_details', ['id' => $paymentDetailId]);
    }

    public function test_invoice_keeps_a_snapshot_when_its_payment_profile_changes_or_is_deleted(): void
    {
        $currency = Currency::factory()->create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'rate' => 1,
            'is_base' => true,
            'is_active' => true,
        ]);
        $applicant = Applicant::factory()->create();
        $service = Service::factory()->create([
            'currency_id' => $currency->id,
            'fee' => 250,
        ]);
        $contract = Contract::create([
            'contract_type_id' => ContractType::query()->where('slug', 'customer')->value('id'),
            'applicant_id' => $applicant->id,
            'name' => $service->type, 'type' => 'Customer', 'status' => 'active',
            'start_date' => '2026-09-01', 'counterparty' => $applicant->name,
            'content' => '', 'is_custom' => true,
        ]);
        $applicantService = $contract->contractServices()->create(['service_id' => $service->id]);
        $paymentDetail = PaymentDetail::create([
            'method' => 'Bank transfer',
            'instruction' => "Account: 12345\nReference the invoice number.",
        ]);

        $response = $this->postJson('/api/invoices', [
            'applicant_id' => $applicant->id,
            'currency_id' => $currency->id,
            'rate' => 1,
            'services' => [['contract_service_id' => $applicantService->id, 'quantity' => 1]],
            'tax_percent' => 0,
            'discount' => 0,
            'payment_detail_id' => $paymentDetail->id,
        ])->assertCreated()
            ->assertJsonPath('data.payment_detail_id', $paymentDetail->id)
            ->assertJsonPath('data.payment_method', 'Bank transfer')
            ->assertJsonPath('data.payment_instructions.0', 'Account: 12345')
            ->assertJsonPath('data.payment_instructions.1', 'Reference the invoice number.');

        $invoiceId = $response->json('data.id');

        $paymentDetail->update([
            'method' => 'Cash',
            'instruction' => 'Pay at reception.',
        ]);
        $paymentDetail->delete();

        $invoice = Invoice::findOrFail($invoiceId);
        $this->assertSame('Bank transfer', $invoice->payment_method);
        $this->assertSame(
            ['Account: 12345', 'Reference the invoice number.'],
            $invoice->payment_instructions,
        );
    }
}
