<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\OutsourcingAccrual;
use App\Models\OutsourcingSettlement;
use App\Services\CurrencyConversionService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OutsourcingController extends Controller
{
    public function __construct(private readonly CurrencyConversionService $currencies) {}

    public function accruals(Request $request)
    {
        $data = $request->validate(['company_id' => ['nullable', 'integer', 'exists:companies,id'], 'status' => ['nullable', 'in:pending,approved,paid,void']]);
        return response()->json(['data' => OutsourcingAccrual::with(['company', 'service', 'invoice', 'income', 'baseCurrency'])
            ->when($data['company_id'] ?? null, fn ($q, $id) => $q->where('company_id', $id))
            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest('earned_at')->latest('id')->get()]);
    }

    public function settlements(Request $request)
    {
        $data = $request->validate(['company_id' => ['nullable', 'integer', 'exists:companies,id'], 'status' => ['nullable', 'in:draft,approved,paid,cancelled']]);
        return response()->json(['data' => OutsourcingSettlement::with(['company', 'baseCurrency', 'recorder', 'approver', 'payer'])
            ->withCount('accruals')->when($data['company_id'] ?? null, fn ($q, $id) => $q->where('company_id', $id))
            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))->latest('id')->get()]);
    }

    public function storeAdjustment(Request $request)
    {
        $data = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'base_currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'effective_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'not_in:0'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $company = Company::query()->where('is_active', true)->findOrFail($data['company_id']);
        $entry = OutsourcingAccrual::create([
            'company_id' => $company->id,
            'base_currency_id' => $data['base_currency_id'],
            'earned_at' => $data['effective_date'],
            'source_payment_base_amount' => 0,
            'eligible_base_amount' => 0,
            'percentage' => 0,
            'outsourcing_base_amount' => round((float) $data['amount'], 6),
            'entry_type' => 'adjustment',
            'adjustment_reason' => $data['reason'],
            'recorded_by' => $request->user()->id,
            'status' => 'pending',
        ]);

        return response()->json(['data' => $entry->load(['company', 'baseCurrency', 'recorder'])], 201);
    }

    public function storeSettlement(Request $request)
    {
        $data = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'], 'base_currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'period_start' => ['required', 'date'], 'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'accrual_ids' => ['nullable', 'array'], 'accrual_ids.*' => ['integer', 'distinct', 'exists:outsourcing_accruals,id'], 'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $settlement = DB::transaction(function () use ($data, $request) {
            $company = Company::query()->lockForUpdate()->findOrFail($data['company_id']);
            $accruals = OutsourcingAccrual::query()->lockForUpdate()->where('company_id', $company->id)->where('base_currency_id', $data['base_currency_id'])
                ->where('status', 'pending')->whereNull('outsourcing_settlement_id')->whereBetween('earned_at', [$data['period_start'], $data['period_end']])
                ->when(! empty($data['accrual_ids']), fn ($q) => $q->whereIn('id', $data['accrual_ids']))->get();
            if ($accruals->isEmpty() || (float) $accruals->sum('outsourcing_base_amount') <= 0) {
                throw ValidationException::withMessages(['accrual_ids' => ['business.no_pending_outsourcing']]);
            }
            $super = $request->user()->isSuperAdmin();
            $settlement = OutsourcingSettlement::create([
                'company_id' => $company->id, 'base_currency_id' => $data['base_currency_id'], 'period_start' => $data['period_start'], 'period_end' => $data['period_end'],
                'gross_base_amount' => round((float) $accruals->sum('outsourcing_base_amount'), 6), 'status' => $super ? 'approved' : 'draft', 'notes' => $data['notes'] ?? null,
                'recorded_by' => $request->user()->id, 'approved_by' => $super ? $request->user()->id : null, 'approved_at' => $super ? now() : null,
            ]);
            OutsourcingAccrual::whereKey($accruals->modelKeys())->update(['outsourcing_settlement_id' => $settlement->id, 'status' => $super ? 'approved' : 'pending']);
            return $settlement;
        });
        return response()->json(['data' => $settlement->load(['company', 'baseCurrency'])->loadCount('accruals')], 201);
    }

    public function approve(Request $request, OutsourcingSettlement $outsourcingSettlement)
    {
        $settlement = DB::transaction(function () use ($request, $outsourcingSettlement) {
            $settlement = OutsourcingSettlement::query()->lockForUpdate()->findOrFail($outsourcingSettlement->id);
            if ($settlement->status !== 'draft' || (! $request->user()->isSuperAdmin() && $settlement->recorded_by === $request->user()->id)) abort(422, 'business.outsourcing_approval_invalid');
            $settlement->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
            $settlement->accruals()->update(['status' => 'approved']); return $settlement;
        });
        return response()->json(['data' => $settlement]);
    }

    public function pay(Request $request, OutsourcingSettlement $outsourcingSettlement)
    {
        $data = $request->validate(['payment_date' => ['required', 'date'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $settlement = DB::transaction(function () use ($request, $data, $outsourcingSettlement) {
            $settlement = OutsourcingSettlement::with(['company', 'baseCurrency'])->lockForUpdate()->findOrFail($outsourcingSettlement->id);
            if ($settlement->status === 'draft' && $request->user()->isSuperAdmin()) { $settlement->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]); $settlement->accruals()->update(['status' => 'approved']); }
            if ($settlement->status !== 'approved' || (! $request->user()->isSuperAdmin() && $settlement->approved_by === $request->user()->id) || Carbon::parse($data['payment_date'])->lt($settlement->period_end)) abort(422, 'business.outsourcing_payment_invalid');
            $currency = Currency::query()->lockForUpdate()->findOrFail($settlement->base_currency_id); $snapshot = $this->currencies->snapshot($currency);
            $expense = Expense::create(['company_id' => $settlement->company_id, 'currency_id' => $currency->id, 'base_currency_id' => $snapshot['base_currency_id'], 'currency' => $currency->code, 'type' => 'Outsourced Services', 'description' => "Outsourcing {$settlement->settlement_number} for {$settlement->company->legal_name}", 'amount' => $settlement->gross_base_amount, 'quantity' => 1, 'cnv_curr_shrt' => $snapshot['cnv_curr_shrt'], 'base_curr_shrt' => $snapshot['base_curr_shrt'], 'rate' => $snapshot['rate'], 'date' => $data['payment_date'], 'recorded_by' => $request->user()->id]);
            $settlement->update(['status' => 'paid', 'paid_by' => $request->user()->id, 'paid_at' => now(), 'payment_date' => $data['payment_date'], 'expense_id' => $expense->id, 'notes' => $data['notes'] ?? $settlement->notes]); $settlement->accruals()->update(['status' => 'paid']); return $settlement;
        });
        return response()->json(['data' => $settlement]);
    }

    public function cancel(OutsourcingSettlement $outsourcingSettlement)
    {
        $settlement = DB::transaction(function () use ($outsourcingSettlement) { $settlement = OutsourcingSettlement::query()->lockForUpdate()->findOrFail($outsourcingSettlement->id); if (! in_array($settlement->status, ['draft', 'approved'], true)) abort(422, 'business.outsourcing_settlement_immutable'); $settlement->accruals()->update(['status' => 'pending', 'outsourcing_settlement_id' => null]); $settlement->update(['status' => 'cancelled']); return $settlement; });
        return response()->json(['data' => $settlement]);
    }
}
