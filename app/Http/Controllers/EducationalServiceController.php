<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\Contract;
use App\Models\ContractService;
use App\Models\ContractType;
use App\Models\Country;
use App\Models\Currency;
use App\Models\EducationLevel;
use App\Models\Employee;
use App\Models\Language;
use App\Models\Package;
use App\Models\Service;
use App\Services\Contracts\ServiceContractGenerator;
use App\Services\EducationalBonusSourceService;
use App\Services\EducationalWorkflowNotificationService;
use App\Services\PackageIssuanceService;
use App\Support\EmployeeScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Scholarship / test-offer sales workspace.
 *
 * Every sale is a contract under the hood: this controller reads and
 * writes `contract_service` lines (joined to their parent contract) and
 * presents them in the historical per-sale shape the workspace UI consumes.
 */
class EducationalServiceController extends Controller
{
    public function __construct(
        private readonly EducationalBonusSourceService $bonusSources,
        private readonly PackageIssuanceService $packages,
        private readonly EducationalWorkflowNotificationService $notifications,
        private readonly ServiceContractGenerator $serviceContracts,
    ) {}

    public function index(Request $request, string $type)
    {
        $page = $this->scopeToActor($this->query($type), $request)
            ->latest('contract_service.created_at')->latest('contract_service.id')
            ->paginate($request->integer('per_page', 50));
        $page->through(fn (ContractService $service) => $this->present($service));

        return response()->json(['success' => true, 'data' => $page]);
    }

    public function show(Request $request, string $type, int $id)
    {
        $record = $this->scopeToActor($this->query($type), $request)->findOrFail($id);

        return response()->json(['success' => true, 'data' => $this->present($record)]);
    }

    public function options(Request $request)
    {
        $employeeQuery = Employee::query()->orderBy('name');
        $applicantQuery = Applicant::query()->latest();
        $contractQuery = Contract::query()->whereNotNull('employee_id')->whereNotNull('applicant_id')->latest();
        if (! $this->canViewAll($request)) {
            $employeeIds = app(EmployeeScope::class)->visibleEmployeeIds($request->user());
            $employeeQuery->whereIn('id', $employeeIds);
            $applicantQuery->whereIn('responsible_employee_id', $employeeIds);
            $contractQuery->whereIn('employee_id', $employeeIds);
        }
        return response()->json(['success' => true, 'data' => [
            'employees' => $employeeQuery->get(['id', 'name']),
            'applicants' => $applicantQuery->get(['id', 'name']),
            'currencies' => Currency::query()->where('is_active', true)->orderBy('code')->get(['id', 'name', 'code', 'symbol', 'is_base']),
            'package_types' => Package::query()->with('services:id,type,category')->where('is_active', true)->orderBy('name')->get(['id', 'name', 'fee', 'currency_id']),
            'services' => Service::query()->where('status', 'active')->whereIn('category', ['scholarship', 'test_offer'])
                ->with('contractTemplate:id,name,service_id')->orderBy('type')
                ->get(['id', 'type', 'category', 'fee', 'currency_id']),
            'countries' => Country::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'education_levels' => EducationLevel::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'languages' => Language::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'contracts' => $contractQuery->get(['id', 'contract_number', 'name', 'employee_id', 'applicant_id']),
        ]]);
    }

    public function store(Request $request, string $type)
    {
        $data = $this->validated($request, $type);
        $this->assertEmployeeAttribution($request, (int) $data['sold_by_employee_id']);
        $this->assertApplicantManageAccess($request, (int) $data['applicant_id']);

        if (! empty($data['package_id'])) {
            abort_unless($request->user()->hasPermission('issue_packages'), 403, 'auth.forbidden');
            $package = Package::query()->whereHas('services', fn ($query) => $query->where('category', $this->category($type)))->findOrFail($data['package_id']);
            $purchase = $this->packages->issue($package, Applicant::findOrFail($data['applicant_id']), [
                'applicant_id' => $data['applicant_id'],
                'sold_by_employee_id' => $data['sold_by_employee_id'],
                'currency_id' => $data['currency_id'] ?? $package->currency_id,
                'fee' => $data['fee'] ?? $package->fee,
                'purchased_at' => $data['sold_at'],
                'notes' => $data['notes'] ?? null,
            ]);
            $this->notifications->packageIssued($purchase, $request->user()?->id);
            $record = $purchase->services()->whereHas('service', fn ($query) => $query->where('category', $this->category($type)))->firstOrFail();
            if (($data['bonus_amount'] ?? 0) > 0 || ! empty($data['bonus_rule_source'])) {
                $this->bonusSources->createFor($record, $this->bonusPayload($data));
            }
        } else {
            // The service's own contract template generates the agreement, so
            // the sale produces a complete, printable contract (not an empty
            // placeholder). Repeat sales become extension contracts.
            $service = isset($data['service_id'])
                ? Service::query()->where('category', $this->category($type))->where('status', 'active')->findOrFail($data['service_id'])
                : Service::query()->where('category', $this->category($type))->where('status', 'active')->orderBy('id')->first()
                    ?? Service::create([
                        'type' => $type === 'scholarships' ? 'Scholarship Assistance' : 'Test Offer',
                        'category' => $this->category($type), 'fee' => $data['fee'],
                        'status' => 'active', 'currency_id' => $data['currency_id'],
                    ]);
            $applicant = Applicant::findOrFail($data['applicant_id']);
            $contract = $this->serviceContracts->createFor($service, $applicant, [
                'fee' => $data['fee'] ?? $service->fee,
                'currency_id' => $data['currency_id'] ?? $service->currency_id,
                'status' => $this->normalizeStatus($data['status'] ?? 'active'),
                'start_date' => $data['sold_at'],
                'sold_by_employee_id' => $data['sold_by_employee_id'],
                'marketer_id' => $data['sold_by_employee_id'],
                'country_id' => $data['country_id'] ?? null,
                'education_level_id' => $data['education_level_id'] ?? null,
                'language_id' => $data['language_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                // The workspace can refuse a repeat sale instead of extending it.
                'extend' => $request->boolean('extend', true),
                'source' => 'educational_workspace',
                'user_id' => $request->user()?->id,
            ]);
            $record = $contract->contractServices()->firstOrFail();
            $this->bonusSources->createFor($record, $this->bonusPayload($data));
            $this->notifications->contractGenerated($contract, $request->user()?->id);
        }

        return response()->json(['success' => true, 'data' => $this->present($this->load($record))], 201);
    }

    public function update(Request $request, string $type, int $id)
    {
        $record = $this->scopeToActor($this->query($type), $request, manage: true)->findOrFail($id);
        abort_if($record->customer_package_id, 422, 'business.package_service_managed_as_bundle');
        abort_if($record->bonusSource()->where('status', 'paid')->exists(), 422, 'business.paid_bonus_source_immutable');
        $data = $this->validated($request, $type, $record, true);
        if (isset($data['sold_by_employee_id'])) {
            $this->assertEmployeeAttribution($request, (int) $data['sold_by_employee_id']);
        }
        $this->assertApplicantManageAccess($request, (int) ($data['applicant_id'] ?? $record->applicant_id));

        DB::transaction(function () use ($record, $data): void {
            $record->update(collect($data)->only(['applicant_id', 'sold_by_employee_id', 'currency_id', 'fee', 'country_id', 'education_level_id', 'language_id', 'notes'])->all());
            if (isset($data['status'])) {
                $record->contract()->update(['status' => $this->normalizeStatus($data['status'])]);
            }
            if (isset($data['sold_at'])) {
                $record->contract()->update(['start_date' => $data['sold_at']]);
            }
            $this->bonusSources->syncForService($record->fresh());
            $bonus = $this->bonusPayload($data);
            if ($bonus !== []) {
                $record->bonusSource()->update($bonus);
            }
            if (($data['status'] ?? null) === 'cancelled') {
                $record->bonusSource()->update(['status' => 'void']);
            }
        });

        return response()->json(['success' => true, 'data' => $this->present($this->load($record->fresh()))]);
    }

    public function destroy(Request $request, string $type, int $id)
    {
        $record = $this->scopeToActor($this->query($type), $request, manage: true)->findOrFail($id);
        $this->assertApplicantManageAccess($request, (int) $record->applicant_id);
        abort_if($record->customer_package_id, 422, 'business.package_service_managed_as_bundle');
        abort_if($record->bonusSource()->where('status', 'paid')->exists(), 422, 'business.paid_bonus_source_immutable');
        DB::transaction(function () use ($record): void {
            $contract = $record->contract;
            $record->bonusSource()->update(['status' => 'void']);
            $record->delete();
            // Standalone workspace sales own their contract; remove the
            // orphan so no empty agreement is left behind.
            if ($contract && $contract->is_custom && ! $contract->contractServices()->exists()) {
                $contract->delete();
            }
        });

        return response()->noContent();
    }

    private function query(string $type)
    {
        return ContractService::query()->whereHas('service', fn ($query) => $query->where('category', $this->category($type)))
            ->with($this->relations());
    }

    private function relations(): array
    {
        return ['service.currency', 'contract.applicant:id,name', 'contract.parentContract:id,contract_number', 'contract:id,applicant_id,status,start_date,contract_role,parent_contract_id,contract_number,source_service_id', 'applicant:id,name', 'soldByEmployee:id,name',
            'currency:id,name,code,symbol', 'customerPackage.package:id,name', 'country:id,name', 'educationLevel:id,name', 'language:id,name', 'bonusSource'];
    }

    private function load(ContractService $record): ContractService
    {
        return $record->load($this->relations());
    }

    private function present(ContractService $record): array
    {
        $contract = $record->contract;
        $applicant = $contract?->applicant ?? $record->applicant;

        return [
            'id' => $record->id, 'applicant_id' => $record->applicant_id ?? $contract?->applicant_id,
            'service_id' => $record->service_id, 'service' => $record->service, 'employee_id' => $record->sold_by_employee_id,
            'currency_id' => $record->currency_id ?? $record->service->currency_id,
            'package_type_id' => $record->customerPackage?->package_id,
            'date' => $contract?->start_date?->toDateString() ?? $record->created_at->toDateString(),
            'fee' => $record->fee ?? $record->service->fee, 'status' => $contract?->status ?? 'active',
            'applicant' => $applicant, 'employee' => $record->soldByEmployee,
            'currency' => $record->currency ?? $record->service->currency, 'package_type' => $record->customerPackage?->package,
            'country' => $record->country, 'education_level' => $record->educationLevel, 'language' => $record->language,
            'country_id' => $record->country_id, 'education_level_id' => $record->education_level_id, 'language_id' => $record->language_id,
            'bonus_source' => $record->bonusSource,
            'contract_id' => $contract?->id,
            'contract_number' => $contract?->contract_number,
            'contract_role' => $contract?->contract_role ?? Contract::ROLE_PRIMARY,
            'parent_contract_id' => $contract?->parent_contract_id,
            'parent_contract_number' => $contract?->parentContract?->contract_number,
        ];
    }

    private function normalizeStatus(string $status): string
    {
        return match ($status) {
            'paused' => 'terminated',
            'draft', 'active', 'completed', 'terminated', 'cancelled' => $status,
            default => 'active',
        };
    }

    private function category(string $type): string
    {
        return match ($type) {
            'scholarships' => 'scholarship', 'test-offers' => 'test_offer', default => abort(404)
        };
    }

    private function scopeToActor($query, Request $request, bool $manage = false)
    {
        if (! $this->canViewAll($request)) {
            $scope = app(EmployeeScope::class);
            $employeeIds = $manage
                ? $scope->manageableEmployeeIds($request->user())
                : $scope->visibleEmployeeIds($request->user());
            $query->whereIn('sold_by_employee_id', $employeeIds);
        }

        return $query;
    }

    private function canViewAll(Request $request): bool
    {
        return $request->user()->isSuperAdmin() || $request->user()->hasPermission('view_all_applicants');
    }

    private function assertEmployeeAttribution(Request $request, int $employeeId): void
    {
        if (! $this->canViewAll($request)) {
            abort_unless(app(EmployeeScope::class)->canManageOwnedBy($request->user(), $employeeId), 403, 'auth.forbidden');
        }
    }

    private function assertApplicantManageAccess(Request $request, int $applicantId): void
    {
        if ($this->canViewAll($request)) {
            return;
        }

        $ownerId = Applicant::query()->whereKey($applicantId)->value('responsible_employee_id');
        abort_unless(app(EmployeeScope::class)->canManageOwnedBy($request->user(), $ownerId), 403, 'auth.forbidden');
    }

    private function validated(Request $request, string $type, ?ContractService $record = null, bool $partial = false): array
    {
        $presence = $partial ? 'sometimes' : 'required';
        $data = $request->validate([
            'applicant_id' => [$presence, 'integer', 'exists:applicants,id'],
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')->where('category', $this->category($type))->where('status', 'active')->whereNull('deleted_at')],
            'employee_id' => [$presence, 'integer', 'exists:employees,id'], 'sold_by_employee_id' => ['sometimes', 'integer', 'exists:employees,id'],
            'currency_id' => [$presence, 'integer', 'exists:currencies,id'], 'package_type_id' => ['nullable', 'integer', 'exists:packages,id'],
            'package_id' => ['nullable', 'integer', 'exists:packages,id'], 'date' => [$presence, 'date'], 'sold_at' => ['sometimes', 'date'],
            'fee' => [$presence, 'numeric', 'gte:0'], 'status' => ['sometimes', 'in:active,paused,completed,terminated,cancelled'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'], 'education_level_id' => ['nullable', 'integer', 'exists:education_levels,id'],
            'language_id' => ['nullable', 'integer', 'exists:languages,id'], 'notes' => ['nullable', 'string', 'max:5000'],
            'bonus_amount' => ['sometimes', 'numeric', 'gte:0'], 'bonus_rule_source' => ['sometimes', 'nullable', 'string', 'max:255'],
            'bonus_status' => ['sometimes', 'in:pending,approved,paid,void'],
        ]);
        $data['sold_by_employee_id'] = $data['sold_by_employee_id'] ?? $data['employee_id'] ?? $record?->sold_by_employee_id;
        $data['sold_at'] = $data['sold_at'] ?? $data['date'] ?? $record?->contract?->start_date?->toDateString();
        $data['package_id'] = $data['package_id'] ?? $data['package_type_id'] ?? null;
        unset($data['employee_id'], $data['date'], $data['package_type_id']);

        $applicantId = array_key_exists('applicant_id', $data) ? $data['applicant_id'] : $record?->applicant_id;
        if ($applicantId) {
            $case = Applicant::findOrFail($applicantId);
            foreach (['country_id', 'education_level_id', 'language_id'] as $field) {
                if (isset($data[$field]) && $case->{$field} && (int) $data[$field] !== (int) $case->{$field}) {
                    throw ValidationException::withMessages([$field => ['The selected value must match the linked applicant case.']]);
                }
                if (! array_key_exists($field, $data)) {
                    $data[$field] = $case->{$field};
                }
            }
        }

        return $data;
    }

    private function bonusPayload(array $data): array
    {
        return array_filter(['bonus_amount' => $data['bonus_amount'] ?? null, 'rule_source' => $data['bonus_rule_source'] ?? null, 'status' => $data['bonus_status'] ?? null], fn ($value) => $value !== null);
    }
}
