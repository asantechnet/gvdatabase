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

            $lines = $this->splitFee($package, $saleFee === null ? null : (float) $saleFee, $saleCurrencyId);

            // A package with its own contract template is sold as ONE agreement
            // covering every member service — exactly like assigning a service,
            // but with many lines. Without a template the historical behaviour
            // is kept: one contract per member service.
            if ($package->primaryContractTemplate()) {
                $this->serviceContracts->createForPackage($package, $applicant, [
                    'package_lines' => $lines,
                    'customer_package_id' => $purchase->id,
                    'status' => 'active',
                    'start_date' => $purchase->purchased_at->toDateString(),
                    'sold_by_employee_id' => $purchase->sold_by_employee_id,
                    'marketer_id' => $purchase->sold_by_employee_id ?? $applicant->marketer_id,
                    'country_id' => $applicant->country_id,
                    'education_level_id' => $applicant->education_level_id,
                    'language_id' => $applicant->language_id,
                    'notes' => "Issued once through package #{$purchase->id}: {$package->name}",
                    'source' => 'package_issuance',
                ]);

                return $purchase->load(['package.services.currency', 'applicant', 'soldByEmployee', 'currency', 'services.service.currency', 'services.contract']);
            }

            // Legacy path: one contract PER packaged service, each holding
            // exactly one service line.
            foreach ($package->services as $service) {
                // The service's own contract template generates the agreement
                // (auto-creating one when the catalogue entry has none) so a
                // package sale also produces a complete, printable contract.
                $this->serviceContracts->createFor($service, $applicant, [
                    'fee' => $lines[$service->id]['fee'],
                    'currency_id' => $lines[$service->id]['currency_id'],
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

    /**
     * Split the sale price across the member services, keyed by service id.
     *
     * Without an overriding sale price every member keeps its allocated share
     * of the package price (or its own fee). With one, the shares scale
     * proportionally to the member fees and the rounding remainder lands on
     * the last service.
     *
     * @return array<int, array{fee: mixed, currency_id: mixed}>
     */
    private function splitFee(Package $package, ?float $saleFee, mixed $saleCurrencyId): array
    {
        $weights = $package->services->mapWithKeys(fn ($service) => [$service->id => max((float) $service->fee, 1.0)]);
        $weightTotal = (float) $weights->sum();
        $remaining = $saleFee;
        $currencyPrecision = $saleCurrencyId ? (int) (Currency::query()->whereKey($saleCurrencyId)->value('decimal_places') ?? 2) : 2;
        $lastId = $package->services->last()->id;

        $lines = [];
        foreach ($package->services as $service) {
            if ($saleFee === null) {
                $lines[$service->id] = [
                    'fee' => $service->pivot->allocated_fee ?? $service->fee,
                    'currency_id' => $service->currency_id,
                ];
            } else {
                $fee = $service->id === $lastId
                    ? round($remaining, $currencyPrecision)
                    : round($saleFee * ((float) $weights[$service->id] / $weightTotal), $currencyPrecision);
                $remaining = round($remaining - $fee, $currencyPrecision);
                $lines[$service->id] = ['fee' => $fee, 'currency_id' => $saleCurrencyId];
            }
        }

        return $lines;
    }
}
