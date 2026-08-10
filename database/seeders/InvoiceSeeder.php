<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\Customer;
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

        Customer::with('services.currency')->take(2)->get()->each(function (Customer $customer) use ($currency) {
            if ($customer->services->isEmpty()) {
                return;
            }

            $subtotal = 0.0;
            $pivotRows = [];

            foreach ($customer->services as $service) {
                $convertedFee = round(
                    ((float) $service->fee / (float) $service->currency->rate_against_base)
                    * (float) $currency->rate_against_base,
                    $currency->decimal_places
                );
                $subtotal += $convertedFee;

                $pivotRows[$service->id] = [
                    'snapshot_name' => $service->type,
                    'snapshot_service_fee' => $service->fee,
                    'snapshot_service_currency_code' => $service->currency->code,
                    'snapshot_service_currency_symbol' => $service->currency->symbol,
                    'snapshot_service_currency_rate' => $service->currency->rate_against_base,
                    'snapshot_invoice_currency_rate' => $currency->rate_against_base,
                    'snapshot_converted_fee' => $convertedFee,
                    'quantity' => 1,
                    'line_total' => $convertedFee,
                ];
            }

            $invoice = Invoice::create([
                'customer_id' => $customer->id,
                'currency_id' => $currency->id,
                'class_type' => 'Standard',
                'deptor' => $customer->name,
                'status' => 'Issued',
                'tax_percent' => 0,
                'currency_name' => $currency->name,
                'currency_rate' => $currency->rate_against_base,
                'currency_symbol' => $currency->symbol,
                'currency_shortname' => $currency->code,
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
