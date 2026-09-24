<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\Contract;
use App\Models\ContractService;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerPackage;
use App\Models\Package;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PackageIssuanceService
{
    /** @param array<string, mixed> $data */
    public function issue(Package $package, Customer $customer, array $data): CustomerPackage
    {
        $package->loadMissing('services.currency');
        if (! $package->is_active || $package->services->isEmpty()) {
            throw ValidationException::withMessages(['package_id' => ['Choose an active package that contains at least one service.']]);
        }

        $applicant = isset($data['applicant_id']) ? Applicant::query()->findOrFail($data['applicant_id']) : null;
        if ($applicant && (int) $applicant->customer_id !== (int) $customer->id) {
            throw ValidationException::withMessages(['applicant_id' => ['The applicant must belong to the selected customer.']]);
        }

        $issuanceKey = $applicant
            ? "applicant:{$applicant->id}:package:{$package->id}"
            : "customer:{$customer->id}:package:{$package->id}";

        return DB::transaction(function () use ($package, $customer, $applicant, $data, $issuanceKey): CustomerPackage {
            Customer::query()->lockForUpdate()->findOrFail($customer->id);
            if (CustomerPackage::withTrashed()->where('issuance_key', $issuanceKey)->exists()) {
                throw ValidationException::withMessages(['package_id' => ['This package has already been issued to this customer case.']]);
            }

            $saleFee = array_key_exists('fee', $data) ? $data['fee'] : $package->fee;
            $saleCurrencyId = $data['currency_id'] ?? $package->currency_id;
            if ($saleFee !== null && ! $saleCurrencyId) {
                throw ValidationException::withMessages(['currency_id' => ['A currency is required when a package price is used.']]);
            }

            $purchase = CustomerPackage::create([
                'customer_id' => $customer->id,
                'applicant_id' => $applicant?->id,
                'package_id' => $package->id,
                'sold_by_employee_id' => $data['sold_by_employee_id'] ?? null,
                'currency_id' => $saleCurrencyId,
                'fee' => $saleFee,
                'purchased_at' => $data['purchased_at'],
                'status' => 'active',
                'issuance_key' => $issuanceKey,
                'notes' => $data['notes'] ?? null,
            ]);

            $weights = $package->services->mapWithKeys(fn ($service) => [$service->id => max((float) $service->fee, 1.0)]);
            $weightTotal = (float) $weights->sum();
            $remaining = $saleFee === null ? null : (float) $saleFee;
            $currencyPrecision = $saleCurrencyId ? (int) (Currency::query()->whereKey($saleCurrencyId)->value('decimal_places') ?? 2) : 2;
            $lastId = $package->services->last()->id;

            // A package sale is one customer contract PER packaged service:
            // each contract holds exactly one service line.
            $contractTypeId = \App\Models\ContractType::query()->where('slug', 'customer')->value('id');
            abort_unless($contractTypeId, 500, 'Customer contract type is missing.');

            foreach ($package->services as $service) {
                if ($saleFee === null) {
                    $fee = $service->pivot->allocated_fee ?? $service->fee;
                    $currencyId = $service->currency_id;
                } else {
                    $fee = $service->id === $lastId
                        ? round($remaining, $currencyPrecision)
                        : round((float) $saleFee * ((float) $weights[$service->id] / $weightTotal), $currencyPrecision);
                    $remaining = round($remaining - $fee, $currencyPrecision);
                    $currencyId = $saleCurrencyId;
                }

                $contract = Contract::create([
                    'contract_type_id' => $contractTypeId,
                    'customer_id' => $customer->id,
                    'applicant_id' => $applicant?->id,
                    'sold_by_employee_id' => $purchase->sold_by_employee_id,
                    'marketer_id' => $purchase->sold_by_employee_id ?? $customer->marketer_id,
                    'name' => $service->type,
                    'type' => 'Customer',
                    'status' => 'active',
                    'start_date' => $purchase->purchased_at->toDateString(),
                    'counterparty' => $customer->name,
                    'counterparty_company' => $customer->company,
                    'counterparty_email' => $customer->email,
                    'counterparty_contact' => $customer->contact,
                    'counterparty_address' => $customer->address,
                    'content' => '',
                    'is_custom' => true,
                    'snapshot_data' => ['entity' => ['package_id' => $package->id, 'customer_package_id' => $purchase->id, 'services' => [['service_id' => $service->id, 'fee' => $fee, 'currency_id' => $currencyId]]]],
                ]);

                ContractService::create([
                    'contract_id' => $contract->id,
                    'service_id' => $service->id,
                    'customer_package_id' => $purchase->id,
                    'applicant_id' => $applicant?->id,
                    'sold_by_employee_id' => $purchase->sold_by_employee_id,
                    'marketer_id' => $purchase->sold_by_employee_id ?? $customer->marketer_id,
                    'fee' => $fee,
                    'currency_id' => $currencyId,
                    'country_id' => $applicant?->country_id,
                    'education_level_id' => $applicant?->education_level_id,
                    'language_id' => $applicant?->language_id,
                    'notes' => "Issued once through package #{$purchase->id}: {$package->name}",
                ]);
            }

            return $purchase->load(['package.services.currency', 'customer', 'applicant', 'soldByEmployee', 'currency', 'services.service.currency']);
        });
    }
}
