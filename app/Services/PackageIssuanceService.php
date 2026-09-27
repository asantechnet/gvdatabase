<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\Contract;
use App\Models\ContractService;
use App\Models\Currency;
use App\Models\CustomerPackage;
use App\Models\Package;
use App\Services\ContractDocumentDesignService;
use App\Services\Contracts\ServiceContractGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PackageIssuanceService
{
    public function __construct(
        private readonly ContractDocumentDesignService $documentDesigns,
        private readonly ServiceContractGenerator $serviceContracts,
    ) {}

    /** @param array<string, mixed> $data */
    public function issue(Package $package, Applicant $applicant, array $data): CustomerPackage
    {
        $package->loadMissing('services.currency');
        if (! $package->is_active || $package->services->isEmpty()) {
            throw ValidationException::withMessages(['package_id' => ['Choose an active package that contains at least one service.']]);
        }

        $issuanceKey = "applicant:{$applicant->id}:package:{$package->id}";

        return DB::transaction(function () use ($package, $applicant, $data, $issuanceKey): CustomerPackage {
            Applicant::query()->lockForUpdate()->findOrFail($applicant->id);
            if (CustomerPackage::withTrashed()->where('issuance_key', $issuanceKey)->exists()) {
                throw ValidationException::withMessages(['package_id' => ['This package has already been issued to this applicant case.']]);
            }

            $saleFee = array_key_exists('fee', $data) ? $data['fee'] : $package->fee;
            $saleCurrencyId = $data['currency_id'] ?? $package->currency_id;
            if ($saleFee !== null && ! $saleCurrencyId) {
                throw ValidationException::withMessages(['currency_id' => ['A currency is required when a package price is used.']]);
            }

            $purchase = CustomerPackage::create([
                'applicant_id' => $applicant->id,
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

            // A package sale is one contract PER packaged service:
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

                // The service's own contract template generates the agreement
                // (auto-creating one when the catalogue entry has none) so a
                // package sale also produces a complete, printable contract.
                $this->serviceContracts->createFor($service, $applicant, [
                    'fee' => $fee,
                    'currency_id' => $currencyId,
                    'status' => 'active',
                    'start_date' => $purchase->purchased_at->toDateString(),
                    'sold_by_employee_id' => $purchase->sold_by_employee_id,
                    'marketer_id' => $purchase->sold_by_employee_id ?? $applicant->marketer_id,
                    'customer_package_id' => $purchase->id,
                    'country_id' => $applicant->country_id,
                    'education_level_id' => $applicant->education_level_id,
                    'language_id' => $applicant->language_id,
                    'notes' => "Issued once through package #{$purchase->id}: {$package->name}",
                    'source' => 'package_issuance',
                    'snapshot_extra' => ['package_id' => $package->id, 'customer_package_id' => $purchase->id],
                ]);
            }

            return $purchase->load(['package.services.currency', 'applicant', 'soldByEmployee', 'currency', 'services.service.currency']);
        });
    }
}
