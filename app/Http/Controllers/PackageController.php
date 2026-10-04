<?php

namespace App\Http\Controllers;

use App\Http\Resources\PackageResource;
use App\Models\Applicant;
use App\Models\ContractTemplate;
use App\Models\Employee;
use App\Models\Package;
use App\Services\Contracts\ServiceContractGenerator;
use App\Services\EducationalWorkflowNotificationService;
use App\Services\PackageIssuanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The package catalogue: a package is a group of services sold to an
 * applicant for one (different) fee, and otherwise behaves like a service —
 * it carries its own code/fee/currency, owns one contract template, and is
 * assigned to applicant cases through the issue endpoint.
 */
class PackageController extends Controller
{
    public function index(Request $request)
    {
        $rows = Package::query()->with(['services.currency', 'currency', 'contractTemplate'])
            ->when($request->boolean('active'), fn ($query) => $query->where('is_active', true))
            ->orderBy('name')->get();

        // Return the resource directly (not via response()->json) so the
        // standard `{ "data": ... }` envelope is applied for clients.
        return PackageResource::collection($rows);
    }

    public function show(Package $package)
    {
        return new PackageResource($package->load(['services.currency', 'currency', 'contractTemplate']));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $package = DB::transaction(function () use ($data): Package {
            $lines = $this->packingList($data);
            unset($data['services'], $data['service_ids']);
            $data['service_manifest'] = collect($lines)->map(fn ($line) => $line['service_id'])->values()->all();
            $package = $this->restoreOrCreate($data);
            $package->services()->sync($this->servicePivot($lines));

            return $package;
        });

        return (new PackageResource($package->fresh()->load(['services.currency', 'currency', 'contractTemplate'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Package $package)
    {
        $data = $this->validated($request, $package);
        DB::transaction(function () use ($package, $data): void {
            $lines = $this->packingList($data, required: false);
            unset($data['services'], $data['service_ids']);
            if ($lines !== null) {
                $data['service_manifest'] = collect($lines)->map(fn ($line) => $line['service_id'])->values()->all();
            }
            $package->update($data);
            if ($lines !== null) {
                $package->services()->sync($this->servicePivot($lines));
            }
        });

        return new PackageResource($package->fresh()->load(['services.currency', 'currency', 'contractTemplate']));
    }

    public function destroy(Package $package)
    {
        abort_if($package->customerPackages()->exists(), 409, 'business.package_in_use');
        // A generated template is package-owned; detach it rather than deleting
        // it so any history that references it stays intact.
        $package->contractTemplates()->update(['package_id' => null, 'is_active' => false]);
        $package->delete();

        return response()->noContent();
    }

    /**
     * Bind (or create) the contract template that represents this package.
     *
     * A package has exactly one contract template, just like a service.
     * Passing `contract_template_id` attaches an existing, unbound template;
     * omitting it creates a draft template from the package metadata (or
     * updates the package's own template when one already exists).
     */
    public function bindContractTemplate(Request $request, Package $package, ServiceContractGenerator $serviceContracts)
    {
        $data = $request->validate([
            'contract_template_id' => ['nullable', 'integer', 'exists:contract_templates,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'cover_text' => ['nullable', 'string', 'max:2000'],
            'content' => ['nullable', 'string'],
        ]);

        if (! empty($data['contract_template_id'])) {
            $template = ContractTemplate::findOrFail($data['contract_template_id']);
            if ($template->service_id) {
                throw ValidationException::withMessages([
                    'contract_template_id' => ['That contract template is already bound to a service.'],
                ]);
            }
            if ($template->package_id && (int) $template->package_id !== (int) $package->id) {
                throw ValidationException::withMessages([
                    'contract_template_id' => ['That contract template is already bound to another package.'],
                ]);
            }
            $template->services()->detach();
            $template->update(['package_id' => $package->id, 'service_id' => null, 'is_active' => true]);
        } else {
            $template = $serviceContracts->templateForPackage($package, $request->user()?->id);
            $template->update(array_filter([
                'name' => $data['name'] ?? null,
                'cover_text' => $data['cover_text'] ?? null,
                'content' => $data['content'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''));
        }

        return new PackageResource($package->fresh(['services.currency', 'currency', 'contractTemplate']));
    }

    public function issue(Request $request, Package $package, PackageIssuanceService $issuer, EducationalWorkflowNotificationService $notifications)
    {
        $data = $request->validate([
            'applicant_id' => ['required', 'integer', 'exists:applicants,id'],
            'sold_by_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'fee' => ['nullable', 'numeric', 'gte:0'],
            'purchased_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        if (! $request->user()->isSuperAdmin() && array_intersect($request->user()->roleNames(), ['admin', 'manager']) === []) {
            $ownEmployeeId = Employee::query()->where('user_id', $request->user()->id)->value('id');
            abort_unless($ownEmployeeId && (int) ($data['sold_by_employee_id'] ?? 0) === (int) $ownEmployeeId, 403, 'auth.forbidden');
            abort_unless(Applicant::query()->whereKey($data['applicant_id'])->where('responsible_employee_id', $ownEmployeeId)->exists(), 403, 'auth.forbidden');
        }
        $purchase = $issuer->issue($package, Applicant::findOrFail($data['applicant_id']), $data);
        $notifications->packageIssued($purchase, $request->user()?->id);

        return response()->json(['success' => true, 'data' => $purchase], 201);
    }

    private function validated(Request $request, ?Package $package = null): array
    {
        $required = $package ? 'sometimes' : 'required';

        $data = $request->validate([
            'name' => [$required, 'string', 'max:255', Rule::unique('packages')->ignore($package?->id)],
            'code' => ['nullable', 'string', 'max:80', Rule::unique('packages', 'code')->ignore($package?->id)],
            'description' => ['nullable', 'string'],
            'currency_id' => ['nullable', 'integer', 'exists:currencies,id', 'required_with:fee'],
            'fee' => ['nullable', 'numeric', 'gte:0', 'required_with:currency_id'],
            'is_active' => ['sometimes', 'boolean'],
            // Packing list: the member services with their share of the package
            // price. `service_ids` is the legacy shape (no per-service share).
            'services' => [$package ? 'sometimes' : 'required_without:service_ids', 'array', 'min:1'],
            'services.*.service_id' => ['required', 'integer', 'distinct', Rule::exists('services', 'id')->whereNull('deleted_at')->where('status', 'active')],
            'services.*.allocated_fee' => ['nullable', 'numeric', 'gte:0'],
            'service_ids' => [$package ? 'sometimes' : 'required_without:services', 'array', 'min:1'],
            'service_ids.*' => ['integer', 'distinct', Rule::exists('services', 'id')->whereNull('deleted_at')->where('status', 'active')],
        ]);

        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        return $data;
    }

    /**
     * Normalize the packing list to `[{service_id, allocated_fee}]`.
     *
     * @return list<array{service_id: int, allocated_fee: float|null}>|null
     */
    private function packingList(array $data, bool $required = true): ?array
    {
        if (array_key_exists('services', $data)) {
            return collect($data['services'])
                ->map(fn ($line) => [
                    'service_id' => (int) $line['service_id'],
                    'allocated_fee' => $line['allocated_fee'] ?? null,
                ])
                ->values()->all();
        }
        if (array_key_exists('service_ids', $data)) {
            return collect($data['service_ids'])
                ->map(fn ($id) => ['service_id' => (int) $id, 'allocated_fee' => null])
                ->values()->all();
        }

        return $required ? [] : null;
    }

    /** @param list<array{service_id: int, allocated_fee: float|null}> $lines */
    private function servicePivot(array $lines): array
    {
        return collect($lines)->values()->mapWithKeys(fn ($line, $index) => [
            $line['service_id'] => ['allocated_fee' => $line['allocated_fee'], 'sort_order' => $index],
        ])->all();
    }

    /**
     * Re-activate a soft-deleted package with the same name instead of failing
     * the unique check, mirroring the master-data restore behaviour.
     */
    private function restoreOrCreate(array $data): Package
    {
        $trashed = Package::onlyTrashed()->where('name', $data['name'] ?? '')->get();
        if ($trashed->count() > 1) {
            throw ValidationException::withMessages([
                'name' => ['A deleted package conflicts with these unique values. Restore or rename it first.'],
            ]);
        }
        if ($trashed->isNotEmpty()) {
            $package = $trashed->first();
            $package->restore();
            $package->update($data);

            return $package;
        }

        return Package::create($data);
    }
}
