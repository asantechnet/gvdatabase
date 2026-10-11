<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\ContractDocumentDesign;
use App\Models\ContractType;
use Illuminate\Support\Collection;

/**
 * Single source of truth for contract artwork scoping.
 *
 *  - customer contracts may scope artwork per service and the service design
 *    wins over the customer-wide design,
 *  - employment / other / custom contract types carry exactly one design that
 *    applies to every contract of that type,
 *  - when nothing matches, the print layer falls back to the general company
 *    artwork kept on the settings record.
 *
 * Both contract creation and the printing read path use this class so the
 * rule is defined once (see contract-template-sanifier.md §3.2).
 */
class ContractDocumentDesignService
{
    /** The only contract type whose contracts may scope artwork per service. */
    public const SERVICE_SCOPED_SLUG = 'customer';

    /** @var Collection<int, ContractDocumentDesign>|null */
    private ?Collection $designs = null;

    /** @var array<int, string>|null */
    private ?array $typeSlugs = null;

    public function supportsServiceScope(?int $contractTypeId): bool
    {
        return $contractTypeId !== null
            && ($this->typeSlugs()[$contractTypeId] ?? null) === self::SERVICE_SCOPED_SLUG;
    }

    /**
     * Resolve the design that applies to a contract of the given type.
     *
     * @param  array<int, string|null>  $services  contract service lines, in contract order
     */
    public function resolve(?int $contractTypeId, array $services = []): ?ContractDocumentDesign
    {
        if ($contractTypeId === null) {
            return null;
        }

        if ($this->supportsServiceScope($contractTypeId)) {
            foreach ($services as $service) {
                if ($service === null) {
                    continue;
                }

                $design = $this->designs()->first(fn (ContractDocumentDesign $design) => (int) $design->contract_type_id === $contractTypeId
                    && $design->service !== null
                    && $design->service === $service);

                if ($design) {
                    return $design;
                }
            }
        }

        return $this->designs()->first(fn (ContractDocumentDesign $design) => (int) $design->contract_type_id === $contractTypeId
            && $design->service === null);
    }

    /** Resolve the design for a contract, using its own service lines. */
    public function resolveForContract(Contract $contract): ?ContractDocumentDesign
    {
        $services = $contract->service ? [$contract->service] : [];

        return $this->resolve($contract->contract_type_id, $services);
    }

    /** The snapshot payload frozen onto a contract when it is issued. */
    public function snapshot(?ContractDocumentDesign $design): ?array
    {
        if (! $design) {
            return null;
        }

        return [
            'id' => $design->id,
            'letterhead' => $design->letterhead,
            'cover_design' => $design->cover_design,
            'safe_x' => (float) $design->safe_x,
            'safe_y' => (float) $design->safe_y,
            'safe_width' => (float) $design->safe_width,
            'safe_height' => (float) $design->safe_height,
        ];
    }

    /**
     * Snapshot to return for a contract: an already issued design stays frozen,
     * while a contract without one inherits the current type/service design so
     * its print layer uses the artwork and sizing configured today.
     *
     * @return array<string, mixed>
     */
    public function snapshotForContract(Contract $contract): array
    {
        $snapshot = $contract->snapshot_data ?? [];

        if (is_array($snapshot['document_design'] ?? null)) {
            return $snapshot;
        }

        if ($design = $this->resolveForContract($contract)) {
            $snapshot['document_design'] = $this->snapshot($design);
        }

        return $snapshot;
    }

    /** Drop the per-instance caches (used after a design is written). */
    public function forget(): void
    {
        $this->designs = null;
        $this->typeSlugs = null;
    }

    /** @return Collection<int, ContractDocumentDesign> */
    private function designs(): Collection
    {
        return $this->designs ??= ContractDocumentDesign::query()
            ->select(['id', 'contract_type_id', 'service', 'letterhead', 'cover_design', 'safe_x', 'safe_y', 'safe_width', 'safe_height'])
            ->get();
    }

    /** @return array<int, string> */
    private function typeSlugs(): array
    {
        return $this->typeSlugs ??= ContractType::query()->withTrashed()->pluck('slug', 'id')->all();
    }
}
