<?php

namespace App\Http\Controllers;

use App\Http\Resources\CommissionAccrualResource;
use App\Http\Resources\CommissionSettlementResource;
use App\Models\Applicant;
use App\Models\ApplicantStage;
use App\Models\CommissionAccrual;
use App\Models\CommissionSettlement;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\Expense;
use App\Services\CommissionService;
use App\Services\CommissionSlipService;
use App\Services\CurrencyConversionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommissionController extends Controller
{
    public function __construct(
        private readonly CurrencyConversionService $currencies,
        private readonly CommissionService $commissions,
    ) {}

    public function accruals(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'status' => ['nullable', 'in:pending,approved,paid,void'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $query = CommissionAccrual::with(['employee', 'contract.applicant.currentStage', 'baseCurrency', 'recorder', 'applicantContract', 'academyClass'])
            ->when($validated['employee_id'] ?? null, fn ($q, $id) => $q->where('employee_id', $id))
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($validated['start_date'] ?? null, fn ($q, $date) => $q->whereDate('earned_at', '>=', $date))
            ->when($validated['end_date'] ?? null, fn ($q, $date) => $q->whereDate('earned_at', '<=', $date));

        return CommissionAccrualResource::collection($query->latest('earned_at')->latest('id')->get());
    }

    public function summary(Request $request)
    {
        $validated = $request->validate(['employee_id' => ['nullable', 'integer', 'exists:employees,id']]);
        $rows = CommissionAccrual::query()
            ->selectRaw('base_currency_id, status, COUNT(*) as accrual_count, SUM(commission_base_amount) as amount')
            ->where('status', '!=', 'void')
            ->when($validated['employee_id'] ?? null, fn ($q, $id) => $q->where('employee_id', $id))
            ->groupBy('base_currency_id', 'status')->with('baseCurrency:id,code,symbol,decimal_places')->get();

        return response()->json(['data' => $rows->map(fn ($row) => [
            'base_currency_id' => $row->base_currency_id,
            'currency' => $row->baseCurrency,
            'status' => $row->status,
            'count' => (int) $row->accrual_count,
            'amount' => (float) $row->amount,
        ])->values()]);
    }

    /**
     * Rebuild the unsettled accruals for every completed case. Commission
     * accrues automatically when a case reaches the completed stage; this is a
     * manual reconciliation for cases that were completed before the ledger
     * existed, or while an employee's service rate was missing.
     *
     * Accruals whose case has since left the completed stage are voided by the
     * same pass, so the ledger matches the workflow again.
     */
    public function sync(Request $request)
    {
        $result = DB::transaction(function (): array {
            $cases = Applicant::query()
                ->whereHas('currentStage', fn ($stage) => $stage->where('key', ApplicantStage::COMPLETED_KEY))
                ->with('contracts.applicantContracts.currency')
                ->get();

            foreach ($cases as $case) {
                $this->commissions->syncForApplicant($case);
            }

            // Rebuild completed-class accruals too. A class completed before
            // the employee rate was configured is repaired by the same pass.
            foreach (\App\Models\AcademyClass::query()->whereNotNull('completed_at')->with('teachers.serviceCommissions')->get() as $class) {
                $this->commissions->syncForAcademyClass($class);
            }

            $voided = CommissionAccrual::query()
                ->where('entry_type', 'earned')
                ->where('status', 'pending')
                ->whereNull('commission_settlement_id')
                ->whereHas('contract.applicant', fn ($applicant) => $applicant->whereDoesntHave(
                    'currentStage',
                    fn ($stage) => $stage->where('key', ApplicantStage::COMPLETED_KEY),
                ))
                ->update(['status' => 'void']);

            return ['cases' => $cases->count(), 'voided' => $voided];
        });

        return response()->json(['data' => $result]);
    }

    public function storeAdjustment(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'base_currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'effective_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'not_in:0'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $employee = Employee::findOrFail($validated['employee_id']);
        if (! $employee->is_commission_based) {
            throw ValidationException::withMessages(['employee_id' => ['business.employee_not_commission_based']]);
        }

        $entry = CommissionAccrual::create([
            'employee_id' => $employee->id,
            'base_currency_id' => $validated['base_currency_id'],
            'earned_at' => $validated['effective_date'],
            'source_base_amount' => 0,
            'eligible_base_amount' => 0,
            'percentage' => 0,
            'commission_base_amount' => round((float) $validated['amount'], 6),
            'entry_type' => 'adjustment',
            'adjustment_reason' => $validated['reason'],
            'recorded_by' => $request->user()->id,
            'status' => 'pending',
        ]);

        return (new CommissionAccrualResource($entry->load(['employee', 'baseCurrency', 'recorder'])))
            ->response()->setStatusCode(201);
    }

    public function settlements(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'status' => ['nullable', 'in:draft,approved,paid,cancelled'],
        ]);
        $query = CommissionSettlement::with(['employee', 'baseCurrency', 'recorder', 'approver', 'payer'])
            ->withCount('accruals')
            ->when($validated['employee_id'] ?? null, fn ($q, $id) => $q->where('employee_id', $id))
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status));

        return CommissionSettlementResource::collection($query->latest('id')->get());
    }

    public function show(CommissionSettlement $commissionSettlement): CommissionSettlementResource
    {
        return new CommissionSettlementResource($commissionSettlement->load([
            'employee', 'baseCurrency', 'recorder', 'approver', 'payer',
            'accruals.employee', 'accruals.contract', 'accruals.baseCurrency', 'accruals.academyClass',
        ]));
    }

    /** The printable commission slip for one settlement (employee + office copies). */
    public function slip(CommissionSettlement $commissionSettlement, CommissionSlipService $slips)
    {
        return response()->json([
            'success' => true,
            'data' => $slips->forSettlement($commissionSettlement),
        ]);
    }

    public function storeSettlement(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'base_currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'accrual_ids' => ['nullable', 'array'],
            'accrual_ids.*' => ['integer', 'distinct', 'exists:commission_accruals,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $settlement = DB::transaction(function () use ($validated, $request) {
            $isSuperAdmin = $request->user()->isSuperAdmin();
            $employee = Employee::query()->lockForUpdate()->findOrFail($validated['employee_id']);
            if (! $employee->is_commission_based) {
                throw ValidationException::withMessages(['employee_id' => ['business.employee_not_commission_based']]);
            }

            $query = CommissionAccrual::query()->lockForUpdate()
                ->where('employee_id', $employee->id)
                ->where('base_currency_id', $validated['base_currency_id'])
                ->where('status', 'pending')->whereNull('commission_settlement_id')
                // `earned_at` is a date column stored with a zero time, so a
                // plain between with a date-only upper bound would drop
                // same-day accruals.
                ->whereDate('earned_at', '>=', $validated['period_start'])
                ->whereDate('earned_at', '<=', $validated['period_end']);
            if (! empty($validated['accrual_ids'])) {
                $query->whereIn('id', $validated['accrual_ids']);
            }
            $accruals = $query->get();
            if ($accruals->isEmpty()) {
                throw ValidationException::withMessages(['accrual_ids' => ['business.no_pending_commissions']]);
            }
            if ((float) $accruals->sum('commission_base_amount') <= 0) {
                throw ValidationException::withMessages(['accrual_ids' => ['business.commission_settlement_not_positive']]);
            }

            $settlement = CommissionSettlement::create([
                'employee_id' => $employee->id,
                'base_currency_id' => $validated['base_currency_id'],
                'period_start' => $validated['period_start'],
                'period_end' => $validated['period_end'],
                'gross_base_amount' => round((float) $accruals->sum('commission_base_amount'), 6),
                // Super administrators are trusted to complete the internal
                // review themselves. Everyone else follows draft → approved → paid.
                'status' => $isSuperAdmin ? 'approved' : 'draft',
                'notes' => $validated['notes'] ?? null,
                'recorded_by' => $request->user()->id,
                'approved_by' => $isSuperAdmin ? $request->user()->id : null,
                'approved_at' => $isSuperAdmin ? now() : null,
            ]);
            CommissionAccrual::whereKey($accruals->modelKeys())->update([
                'commission_settlement_id' => $settlement->id,
                'status' => $isSuperAdmin ? 'approved' : 'pending',
            ]);

            return $settlement;
        });

        return (new CommissionSettlementResource($settlement->load(['employee', 'baseCurrency'])->loadCount('accruals')))
            ->response()->setStatusCode(201);
    }

    public function approve(Request $request, CommissionSettlement $commissionSettlement)
    {
        $settlement = DB::transaction(function () use ($request, $commissionSettlement) {
            $settlement = CommissionSettlement::query()->lockForUpdate()->findOrFail($commissionSettlement->id);
            if ($settlement->status !== 'draft') {
                throw ValidationException::withMessages(['settlement' => ['business.commission_settlement_not_draft']]);
            }
            abort_if(
                ! $request->user()->isSuperAdmin()
                && (int) $settlement->recorded_by === (int) $request->user()->id,
                422,
                'business.commission_creator_cannot_approve',
            );
            $settlement->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
            $settlement->accruals()->update(['status' => 'approved']);

            return $settlement;
        });

        return new CommissionSettlementResource($settlement->load(['employee', 'baseCurrency'])->loadCount('accruals'));
    }

    public function pay(Request $request, CommissionSettlement $commissionSettlement)
    {
        $validated = $request->validate([
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $settlement = DB::transaction(function () use ($request, $validated, $commissionSettlement) {
            $settlement = CommissionSettlement::with(['employee', 'baseCurrency'])->lockForUpdate()->findOrFail($commissionSettlement->id);
            if ($settlement->status === 'draft' && $request->user()->isSuperAdmin()) {
                $settlement->update([
                    'status' => 'approved',
                    'approved_by' => $request->user()->id,
                    'approved_at' => now(),
                ]);
                $settlement->accruals()->update(['status' => 'approved']);
            }
            if ($settlement->status !== 'approved') {
                throw ValidationException::withMessages(['settlement' => ['business.commission_settlement_not_approved']]);
            }
            abort_if(
                ! $request->user()->isSuperAdmin()
                && (int) $settlement->approved_by === (int) $request->user()->id,
                422,
                'business.commission_approver_cannot_pay',
            );
            abort_if(Carbon::parse($validated['payment_date'])->lt($settlement->period_end), 422, 'business.commission_payment_before_period_end');

            $currency = Currency::query()->lockForUpdate()->findOrFail($settlement->base_currency_id);
            $snapshot = $this->currencies->snapshot($currency);
            $baseAmount = (float) $settlement->gross_base_amount;

            $expense = Expense::create([
                'currency_id' => $currency->id,
                'base_currency_id' => $snapshot['base_currency_id'],
                'currency' => $currency->code,
                'type' => 'Commissions',
                'description' => "Commission {$settlement->settlement_number} for {$settlement->employee->name}",
                'amount' => $baseAmount,
                'quantity' => 1,
                'cnv_curr_shrt' => $snapshot['cnv_curr_shrt'],
                'base_curr_shrt' => $snapshot['base_curr_shrt'],
                'rate' => $snapshot['rate'],
                'base_amount' => round($baseAmount / max((float) $snapshot['rate'], 0.00000001), 4),
                'date' => $validated['payment_date'],
                'recorded_by' => $request->user()->id,
            ]);
            $settlement->update([
                'status' => 'paid', 'paid_by' => $request->user()->id, 'paid_at' => now(),
                'payment_date' => $validated['payment_date'], 'expense_id' => $expense->id,
                'notes' => $validated['notes'] ?? $settlement->notes,
            ]);
            $settlement->accruals()->update(['status' => 'paid']);

            return $settlement;
        });

        return new CommissionSettlementResource($settlement->load(['employee', 'baseCurrency'])->loadCount('accruals'));
    }

    public function cancel(CommissionSettlement $commissionSettlement)
    {
        $settlement = DB::transaction(function () use ($commissionSettlement) {
            $settlement = CommissionSettlement::query()->lockForUpdate()->findOrFail($commissionSettlement->id);
            if (! in_array($settlement->status, ['draft', 'approved'], true)) {
                throw ValidationException::withMessages(['settlement' => ['business.paid_commission_settlement_immutable']]);
            }
            $settlement->accruals()->update(['status' => 'pending', 'commission_settlement_id' => null]);
            $settlement->update(['status' => 'cancelled']);

            return $settlement;
        });

        return new CommissionSettlementResource($settlement->load(['employee', 'baseCurrency'])->loadCount('accruals'));
    }
}
