<?php

namespace Database\Seeders;

use App\Models\Applicant;
use App\Models\Currency;
use App\Models\Invoice;
use Illuminate\Database\Seeder;

class InvoiceSeeder extends Seeder
{
    public function run(): void
    {
        $currency = Currency::where('is_base', true)->first() ?? Currency::first();

        if (!$currency) {
            return;
        }

        Applicant::with(['contractServices.service.currency', 'contractServices.currency', 'contractServices.contract'])
            ->take(2)->get()->each(function (Applicant $applicant) use ($currency) {
                $lines = $applicant->contractServices->filter(fn ($line) =>
                    $line->contract?->status === 'active' && ! $line->contract?->is_cancelled);

                if ($lines->isEmpty()) {
                    return;
                }

                $subtotal = 0.0;
                $pivotRows = [];

                foreach ($lines as $line) {
                    $service = $line->service;
                    $sourceCurrency = $line->currency ?? $service?->currency;
                    $serviceRate = max((float) ($sourceCurrency?->rate ?? 1), 0.00000001);
                    $invoiceRate = max((float) $currency->rate, 0.00000001);
                    $contractedFee = (float) ($line->fee ?? $service?->fee ?? 0);
                    $convertedFee = round(
                        ($contractedFee / $serviceRate) * $invoiceRate,
                        $currency->decimal_places
                    );
                    $subtotal += $convertedFee;

                    $pivotRows[$service->id] = [
                        'contract_service_id' => $line->id,
                        'snapshot_name' => $service->type,
                        'snapshot_service_fee' => $contractedFee,
                        'snapshot_service_currency_code' => $sourceCurrency->code,
                        'snapshot_service_currency_symbol' => $sourceCurrency->symbol,
                        'snapshot_service_rate' => $serviceRate,
                        'snapshot_cnv_curr_shrt' => $currency->code,
                        'snapshot_base_curr_shrt' => $currency->code,
                        'snapshot_rate' => $invoiceRate,
                        'snapshot_invoice_rate' => $invoiceRate,
                        'snapshot_converted_fee' => $convertedFee,
                        'quantity' => 1,
                        'line_total' => $convertedFee,
                    ];
                }

                $invoice = Invoice::create([
                    'applicant_id' => $applicant->id,
                    'currency_id' => $currency->id,
                    'base_currency_id' => $currency->id,
                    'deptor' => $applicant->name,
                    'customer_company' => $applicant->company,
                    'customer_contact' => $applicant->contact,
                    'customer_email' => $applicant->email,
                    'customer_address' => $applicant->address,
                    'customer_tax_number' => $applicant->tax_number,
                    'status' => 'Issued',
                    'tax_percent' => 0,
                    'currency_name' => $currency->name,
                    'currency_symbol' => $currency->symbol,
                    'currency_shortname' => $currency->code,
                    'cnv_curr_shrt' => $currency->code,
                    'base_curr_shrt' => $currency->code,
                    'rate' => 1,
                    'base_amount' => $subtotal,
                    'subtotal' => $subtotal,
                    'amount' => $subtotal,
                    'discount' => 0,
                    'notes' => 'Seeded invoice',
                    'payment_method' => 'Bank Transfer',
                    'payment_instructions' => [],
                ]);

                $invoice->services()->attach($pivotRows);
            });
    }
}
