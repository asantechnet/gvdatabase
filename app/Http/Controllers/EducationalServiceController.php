<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\PackageType;
use App\Models\Scholarship;
use App\Models\TestOffer;
use App\Services\EducationalBonusSourceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EducationalServiceController extends Controller
{
    public function __construct(private readonly EducationalBonusSourceService $bonusSources) {}

    public function index(string $type)
    {
        return response()->json(['success' => true, 'data' => $this->query($type)->latest('date')->latest()->get()]);
    }

    public function show(string $type, int $id)
    {
        return response()->json(['success' => true, 'data' => $this->query($type)->findOrFail($id)]);
    }

    public function options()
    {
        return response()->json(['success' => true, 'data' => [
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'employees' => Employee::query()->orderBy('name')->get(['id', 'name']),
            'applicants' => Applicant::query()->with('customer:id,name')->latest()->get(['id', 'customer_id']),
            'currencies' => Currency::query()->where('is_active', true)->orderBy('code')->get(['id', 'name', 'code', 'symbol', 'is_base']),
            'package_types' => PackageType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'contracts' => Contract::query()
                ->whereNotNull('employee_id')
                ->whereNotNull('customer_id')
                ->latest()
                ->get(['id', 'contract_number', 'name', 'employee_id', 'customer_id', 'applicant_id']),
        ]]);
    }

    public function store(Request $request, string $type)
    {
        $data = $this->validated($request);

        $record = DB::transaction(function () use ($type, $data) {
            /** @var Scholarship|TestOffer $service */
            $service = $this->model($type)::create($data['service']);
            $this->bonusSources->createFor($service, $data['bonus']);

            return $service;
        });

        return response()->json(['success' => true, 'data' => $this->load($record)], 201);
    }

    public function update(Request $request, string $type, int $id)
    {
        /** @var Scholarship|TestOffer $record */
        $record = $this->model($type)::query()->findOrFail($id);
        abort_if($record->bonusSource()->where('status', 'paid')->exists(), 422, 'business.paid_bonus_source_immutable');
        $data = $this->validated($request, $record, true);

        DB::transaction(function () use ($record, $data) {
            $record->update($data['service']);
            $this->bonusSources->syncForService($record->fresh());

            if ($data['bonus'] !== []) {
                $record->bonusSource()->update($data['bonus']);
            }
            if (($data['service']['status'] ?? null) === 'cancelled') {
                $record->bonusSource()->update(['status' => 'void']);
            }
        });

        return response()->json(['success' => true, 'data' => $this->load($record->fresh())]);
    }

    public function destroy(string $type, int $id)
    {
        /** @var Scholarship|TestOffer $record */
        $record = $this->model($type)::query()->findOrFail($id);
        abort_if($record->bonusSource()->where('status', 'paid')->exists(), 422, 'business.paid_bonus_source_immutable');

        DB::transaction(function () use ($record) {
            $record->bonusSource()->update(['status' => 'void']);
            $record->delete();
        });

        return response()->noContent();
    }

    /** @return class-string<Scholarship|TestOffer> */
    private function model(string $type): string
    {
        return match ($type) {
            'scholarships' => Scholarship::class,
            'test-offers' => TestOffer::class,
            default => abort(404),
        };
    }

    private function query(string $type)
    {
        return $this->model($type)::query()->with([
            'customer:id,name', 'employee:id,name', 'applicant:id,customer_id',
            'applicant.customer:id,name', 'currency:id,name,code,symbol',
            'packageType:id,name', 'bonusSource',
        ]);
    }

    private function load(Model $service): Model
    {
        return $service->load([
            'customer:id,name', 'employee:id,name', 'applicant:id,customer_id',
            'applicant.customer:id,name', 'currency:id,name,code,symbol',
            'packageType:id,name', 'bonusSource',
        ]);
    }

    /** @return array{service: array<string, mixed>, bonus: array<string, mixed>} */
    private function validated(Request $request, ?Model $record = null, bool $partial = false): array
    {
        $presence = $partial ? 'sometimes' : 'required';
        $service = $request->validate([
            'customer_id' => [$presence, 'integer', 'exists:customers,id'],
            'applicant_id' => ['nullable', 'integer', 'exists:applicants,id'],
            'employee_id' => [$presence, 'integer', 'exists:employees,id'],
            'currency_id' => [$presence, 'integer', 'exists:currencies,id'],
            'package_type_id' => ['nullable', 'integer', 'exists:package_types,id'],
            'date' => [$presence, 'date'],
            'fee' => [$presence, 'numeric', 'gt:0'],
            'status' => ['sometimes', 'in:active,completed,cancelled'],
            'bonus_amount' => ['sometimes', 'numeric', 'gte:0'],
            'bonus_rule_source' => ['sometimes', 'nullable', 'string', 'max:255'],
            'bonus_status' => ['sometimes', 'in:pending,approved,paid,void'],
        ]);

        $customerId = $service['customer_id'] ?? $record?->customer_id;
        $applicantId = array_key_exists('applicant_id', $service) ? $service['applicant_id'] : $record?->applicant_id;
        if ($applicantId && ! Applicant::query()->whereKey($applicantId)->where('customer_id', $customerId)->exists()) {
            throw ValidationException::withMessages(['applicant_id' => ['The applicant must belong to the selected customer.']]);
        }
        $currencyId = $service['currency_id'] ?? $record?->currency_id;
        if ($currencyId && ! Currency::query()->whereKey($currencyId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['currency_id' => ['The selected currency is not active.']]);
        }

        $bonus = array_filter([
            'bonus_amount' => $service['bonus_amount'] ?? null,
            'rule_source' => $service['bonus_rule_source'] ?? null,
            'status' => $service['bonus_status'] ?? null,
        ], static fn ($value) => $value !== null);
        unset($service['bonus_amount'], $service['bonus_rule_source'], $service['bonus_status']);

        return ['service' => $service, 'bonus' => $bonus];
    }
}
