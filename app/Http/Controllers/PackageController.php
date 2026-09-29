<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\Employee;
use App\Models\Package;
use App\Services\EducationalWorkflowNotificationService;
use App\Services\PackageIssuanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PackageController extends Controller
{
    public function index(Request $request)
    {
        $rows = Package::query()->with(['services.currency', 'currency'])
            ->when($request->boolean('active'), fn ($query) => $query->where('is_active', true))
            ->orderBy('name')->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function show(Package $package)
    {
        return response()->json(['success' => true, 'data' => $package->load(['services.currency', 'currency'])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $package = DB::transaction(function () use ($data): Package {
            $serviceIds = $data['service_ids'];
            unset($data['service_ids']);
            $data['service_manifest'] = array_values($serviceIds);
            $package = Package::create($data);
            $package->services()->sync($this->servicePivot($serviceIds));

            return $package;
        });

        return response()->json(['success' => true, 'data' => $package->load(['services.currency', 'currency'])], 201);
    }

    public function update(Request $request, Package $package)
    {
        $data = $this->validated($request, $package);
        DB::transaction(function () use ($package, $data): void {
            $serviceIds = $data['service_ids'] ?? null;
            unset($data['service_ids']);
            if ($serviceIds !== null) {
                $data['service_manifest'] = array_values($serviceIds);
            }
            $package->update($data);
            if ($serviceIds !== null) {
                $package->services()->sync($this->servicePivot($serviceIds));
            }
        });

        return response()->json(['success' => true, 'data' => $package->fresh()->load(['services.currency', 'currency'])]);
    }

    public function destroy(Package $package)
    {
        abort_if($package->customerPackages()->exists(), 409, 'business.package_in_use');
        $package->delete();

        return response()->noContent();
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

        return $request->validate([
            'name' => [$required, 'string', 'max:255', Rule::unique('packages')->ignore($package?->id)],
            'description' => ['nullable', 'string'],
            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'fee' => ['nullable', 'numeric', 'gte:0', 'required_with:currency_id'],
            'is_active' => ['sometimes', 'boolean'],
            'service_ids' => [$required, 'array', 'min:1'],
            'service_ids.*' => ['integer', 'distinct', Rule::exists('services', 'id')->whereNull('deleted_at')->where('status', 'active')],
        ]);
    }

    private function servicePivot(array $ids): array
    {
        return collect($ids)->values()->mapWithKeys(fn ($id, $index) => [(int) $id => ['sort_order' => $index]])->all();
    }
}
