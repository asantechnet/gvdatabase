<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PaginatesLists;
use App\Http\Resources\IncomeResource;
use App\Models\Applicant;
use App\Models\ApplicantContract;
use App\Models\Currency;
use App\Models\Income;
use App\Services\ApplicantContractPaymentService;
use App\Services\CurrencyConversionService;
use App\Services\CurrencyRateService;
use App\Services\EducationalWorkflowNotificationService;
use App\Services\IncomeReceiptService;
use App\Support\BuiltInService;
use App\Support\PaymentStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IncomeController extends Controller
{
    use PaginatesLists;

    public function __construct(
        private readonly CurrencyConversionService $currencies,
        private readonly EducationalWorkflowNotificationService $notifications,
        private readonly ApplicantContractPaymentService $payments,
        private readonly CurrencyRateService $rates,
    ) {}

    public function index(Request $request)
    {
        $query = Income::with(['applicant', 'applicantContract.contract', 'applicantContract.currency', 'incomeCurrency', 'recorder']);

        if ($request->filled('applicant_id')) {
            $query->where('applicant_id', $request->integer('applicant_id'));
        }

        if ($request->filled('applicant_contract_id')) {
            $query->where('applicant_contract_id', $request->integer('applicant_contract_id'));
        }

        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }

        if ($request->filled('currency_id')) {
            $query->where('currency_id', $request->integer('currency_id'));
        }

        if ($request->has('search') && $request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('description', 'like', '%'.$request->search.'%')
                    ->orWhere('type', 'like', '%'.$request->search.'%');
            });
        }

        $dates = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', $request->filled('date_from') ? 'after_or_equal:date_from' : 'nullable'],
        ]);

        if (! empty($dates['date_from'])) {
            $query->whereDate('date', '>=', $dates['date_from']);
        }

        if (! empty($dates['date_to'])) {
            $query->whereDate('date', '<=', $dates['date_to']);
        }

        $query->latest();

        if ($this->requestedPerPage($request)) {
            return response()->json(['success' => true] + $this->paginatedPayload($request, $query, fn ($rows) => IncomeResource::collection($rows)->resolve()));
        }

        return IncomeResource::collection($query->get());
    }

    public function applicants(Request $request)
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:255']]);
        $query = Applicant::query()->select(['id', 'name', 'contact']);
        $search = trim($data['search'] ?? '');
        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%')->orWhere('contact', 'like', '%'.$search.'%');
                if (ctype_digit($search)) {
                    $query->orWhere('id', (int) $search);
                }
            });
        }

        return response()->json(['success' => true, 'data' => $query->orderByDesc('id')->limit(20)->get()]);
    }

    /**
     * The contracted service lines a payment can be recorded against, so the
     * page can offer the applicant's own services.
     */
    public function applicantContracts(Request $request)
    {
        Validator::make($request->all(), [
            'applicant_id' => 'required|exists:applicants,id',
        ])->validate();

        $lines = ApplicantContract::query()
            ->with(['contract:id,contract_number,status,is_cancelled', 'currency:id,code,symbol,rate,decimal_places'])
            ->where('applicant_id', $request->integer('applicant_id'))
            ->orderByDesc('id')
            ->get()
            ->map(fn (ApplicantContract $line) => $this->linePaymentPayload($line));

        return response()->json(['success' => true, 'data' => $lines]);
    }

    public function paymentOverride(Request $request, ApplicantContract $line)
    {
        $data = $request->validate([
            'amount_paid' => ['present', 'nullable', 'numeric', 'min:0', 'max:9999999999999', 'decimal:0,6'],
            'payment_status' => ['present', 'nullable', Rule::in(PaymentStatus::ALL)],
        ]);

        $line = DB::transaction(function () use ($line, $data) {
            $line = ApplicantContract::query()->lockForUpdate()->findOrFail($line->id);
            $line->amount_paid_overridden = $data['amount_paid'] !== null;
            $line->payment_status_overridden = $data['payment_status'] !== null;
            if ($line->amount_paid_overridden) {
                $line->amount_paid = $data['amount_paid'];
            }
            if ($line->payment_status_overridden) {
                $line->payment_status = $data['payment_status'];
            }
            $line->save();

            return $this->payments->recalculate($line->fresh());
        });

        return response()->json(['success' => true, 'data' => $this->linePaymentPayload($line)]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invoice_id' => ['prohibited'],
            'invoice_amount' => ['prohibited'],
            'company_id' => ['prohibited'],
            'applicant_id' => 'nullable|exists:applicants,id',
            'applicant_contract_id' => 'nullable|exists:applicant_contract,id',
            'type' => ['required', 'string', 'max:255', Rule::exists('finance_types', 'value')->where('kind', 'income')],
            'description' => 'nullable|string|max:65535',
            'amount' => 'required|numeric|gt:0',
            'quantity' => 'required|integer|min:1',
            'currency_id' => 'required|exists:currencies,id',
            'rate' => 'nullable|numeric|min:0.00000001',
            'date' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $validator->validate();

        $data = $validator->validated();
        $data['recorded_by'] = $request->user()->id;
        $data['description'] = $data['description'] ?? '';

        $income = DB::transaction(function () use ($data) {
            if (empty($data['applicant_contract_id'])) {
                unset($data['applicant_contract_id']);
                $this->applyCurrencySnapshot($data);

                return Income::create($data);
            }

            $line = ApplicantContract::query()
                ->whereKey($data['applicant_contract_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $data['applicant_id'] = $line->applicant_id;
            $this->ensureLineCurrency($line, (int) $data['currency_id']);
            $this->applyCurrencySnapshot($data);
            $this->ensureLineCanAcceptPayment($line, $data);

            return Income::create($data);
        });

        $this->notifications->incomeRecorded($income, (int) $request->user()->id);

        return new IncomeResource($income->load(['applicant', 'applicantContract.contract', 'applicantContract.currency', 'incomeCurrency', 'recorder']));
    }

    public function show(Income $income)
    {
        return new IncomeResource($income->load(['applicant', 'applicantContract.contract', 'applicantContract.currency', 'incomeCurrency', 'recorder']));
    }

    /**
     * The printable receipt payload: one service line, its fee/discount/paid/
     * remaining, the party identity, serial number and letterhead artwork.
     */
    public function receipt(Income $income, IncomeReceiptService $receipts)
    {
        return response()->json([
            'success' => true,
            'data' => $receipts->forIncome($income),
        ]);
    }

    public function update(Request $request, Income $income)
    {
        $validator = Validator::make($request->all(), [
            'invoice_id' => ['prohibited'],
            'invoice_amount' => ['prohibited'],
            'company_id' => ['prohibited'],
            'applicant_id' => 'nullable|exists:applicants,id',
            'applicant_contract_id' => 'nullable|exists:applicant_contract,id',
            'type' => ['sometimes', 'string', 'max:255', Rule::exists('finance_types', 'value')->where('kind', 'income')],
            'description' => 'sometimes|nullable|string|max:65535',
            'amount' => 'sometimes|numeric|gt:0',
            'quantity' => 'sometimes|integer|min:1',
            'currency_id' => 'sometimes|exists:currencies,id',
            'rate' => 'nullable|numeric|min:0.00000001',
            // Only required when a value-bearing field actually changes.
            'revaluation_reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'date' => ['sometimes', 'date', 'before_or_equal:today'],
        ]);

        $validator->validate();

        $data = $validator->validated();

        // Protect the issued value: an amount, quantity, currency or rate
        // change is a revaluation and cannot be silent, even through a direct
        // API call. Description, type and date edits stay free.
        $valueFields = ['amount', 'quantity', 'currency_id', 'rate'];
        // A null rate is an ignored field, not a revaluation: it keeps the
        // snapshot exactly as before.
        $revaluation = collect($valueFields)->contains(fn (string $field) => array_key_exists($field, $data)
            && $data[$field] !== null
            && (string) $data[$field] !== (string) $income->{$field});
        if ($revaluation) {
            $reason = trim((string) ($data['revaluation_reason'] ?? ''));
            if ($reason === '') {
                throw ValidationException::withMessages([
                    'revaluation_reason' => ['business.revaluation_reason_required'],
                ]);
            }
            $data['revaluation_reason'] = $reason;
        } else {
            unset($data['revaluation_reason']);
        }

        $income = DB::transaction(function () use ($data, $income) {
            $income = Income::query()->lockForUpdate()->findOrFail($income->id);

            $lineId = array_key_exists('applicant_contract_id', $data)
                ? $data['applicant_contract_id']
                : $income->applicant_contract_id;
            // A description-only edit must not silently re-price the receipt,
            // so the currency snapshot is refreshed only when the payer, the
            // currency or the agreed rate actually changes.
            $requestedCurrency = isset($data['currency_id']) && (int) $data['currency_id'] !== (int) $income->currency_id;
            $requestedRate = isset($data['rate']);
            if (! $requestedRate) {
                unset($data['rate']);
            }

            $lines = ApplicantContract::withTrashed()->whereIn('id', array_filter([
                $income->applicant_contract_id, $lineId,
            ]))->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            if ($lineId) {
                $line = $lines->get($lineId);
                abort_unless($line && (! $line->trashed() || (int) $lineId === (int) $income->applicant_contract_id), 404);
                $data['applicant_id'] = $line->applicant_id;
                $this->ensureLineCurrency($line, (int) ($data['currency_id'] ?? $income->currency_id));
                if ($requestedCurrency) {
                    $this->applyCurrencySnapshot($data);
                } elseif ($requestedRate) {
                    $data['rate'] = $this->rates->remember($income->currency_id, $data['rate']);
                }
                $this->ensureLineCanAcceptPayment($line, array_merge($income->toArray(), $data), $income);
            } else {
                if ($requestedCurrency) {
                    $this->applyCurrencySnapshot($data);
                } elseif ($requestedRate) {
                    $data['rate'] = $this->rates->remember($income->currency_id, $data['rate']);
                }
            }

            $income->update($data);

            return $income;
        });

        return new IncomeResource($income->load(['applicant', 'applicantContract.contract', 'applicantContract.currency', 'incomeCurrency', 'recorder']));
    }

    public function destroy(Income $income)
    {
        DB::transaction(function () use ($income) {
            $income = Income::query()->lockForUpdate()->findOrFail($income->id);
            if ($income->applicant_contract_id) {
                ApplicantContract::withTrashed()->whereKey($income->applicant_contract_id)->lockForUpdate()->first();
            }

            $income->delete();
        });

        return response()->noContent();
    }

    /**
     * A receipt may not exceed what is still owed on the line. When the payer
     * changes, the equivalent of the receipt in the line currency is used.
     */
    private function ensureLineCanAcceptPayment(ApplicantContract $line, array $payment, ?Income $excluding = null): void
    {
        $alreadyPaid = $this->payments->effectivePaidTotal($line, $excluding);
        $remaining = round(max(0, $line->payableTotal() - $alreadyPaid), 6);

        $amount = $this->payments->paymentInLineCurrency(
            $line,
            (float) ($payment['amount'] ?? 0),
            (int) ($payment['quantity'] ?? 1),
            isset($payment['rate']) ? (float) $payment['rate'] : null,
        );

        if ($amount > $remaining + 0.00001) {
            throw ValidationException::withMessages([
                'amount' => ['business.contract_payment_exceeds_balance'],
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function linePaymentPayload(ApplicantContract $line): array
    {
        $payable = $line->payableTotal();
        $paid = $this->payments->effectivePaidTotal($line);

        return [
            'id' => $line->id,
            'contract_id' => $line->contract_id,
            'contract_number' => $line->contract?->contract_number,
            'contract_status' => $line->contract?->status,
            'is_cancelled' => (bool) ($line->contract?->is_cancelled ?? false),
            'service' => $line->serviceKey(),
            'service_label' => $line->serviceLabel(),
            'service_label_key' => BuiltInService::labelKey($line->serviceKey()),
            'fee' => (float) ($line->fee ?? 0),
            'discount' => (float) ($line->discount ?? 0),
            'payable_total' => $payable,
            'amount_paid' => $paid,
            'recorded_amount_paid' => $this->payments->paidTotal($line),
            'amount_paid_overridden' => (bool) $line->amount_paid_overridden,
            'payment_status_overridden' => (bool) $line->payment_status_overridden,
            'remaining_total' => max(0, round($payable - $paid, 6)),
            'payment_status' => $line->payment_status,
            'payment_status_label' => $line->paymentStatusLabel(),
            'currency' => $line->currency ? [
                'id' => $line->currency->id,
                'code' => $line->currency->code,
                'symbol' => $line->currency->symbol,
                'rate' => (float) $line->currency->rate,
                'decimalPlaces' => (int) $line->currency->decimal_places,
            ] : null,
            'currency_rate' => $this->payments->lineRate($line),
            'created_at' => $line->created_at?->toISOString(),
        ];
    }

    private function ensureLineCurrency(ApplicantContract $line, int $currencyId): void
    {
        if ($line->currency_id && (int) $line->currency_id !== $currencyId) {
            throw ValidationException::withMessages([
                'currency_id' => ['business.contract_payment_currency_mismatch'],
            ]);
        }
    }

    private function applyCurrencySnapshot(array &$data): void
    {
        $receiptRate = $this->rates->remember((int) $data['currency_id'], $data['rate'] ?? null);
        $currency = Currency::query()->findOrFail($data['currency_id']);
        $data = [...$data, ...$this->currencies->snapshot($currency)];
        if (array_key_exists('amount', $data)) {
            $data['amount'] = round((float) $data['amount'], $currency->decimal_places);
        }

        // Preserve the exchange rate agreed for this particular receipt.
        if ($receiptRate !== null) {
            $data['rate'] = (float) $receiptRate;
        }
    }
}
