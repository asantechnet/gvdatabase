<?php

namespace App\Http\Controllers;

use App\Http\Resources\CurrencyResource;
use App\Models\Currency;
use App\Models\CurrencyRateHistory;
use App\Models\Position;
use App\Services\CurrencyRateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CurrencyController extends Controller
{
    public function __construct(private CurrencyRateService $rates) {}

    public function index(Request $request)
    {
        $includeInactive = $request->boolean('include_inactive')
            && $request->user()?->hasPermission('view_currencies');

        return response()->json([
            'success' => true,
            'data' => CurrencyResource::collection(
                Currency::query()
                    ->when(! $includeInactive, fn ($query) => $query->where('is_active', true))
                    ->orderByDesc('is_base')->orderBy('code')->get(),
            ),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['code'] = strtoupper($data['code']);

        $currency = DB::transaction(function () use ($data, $request) {
            $hasBase = Currency::query()->lockForUpdate()->where('is_base', true)->exists();
            $data['is_base'] = $data['is_base'] ?? ! $hasBase;
            $data['is_active'] = $data['is_base'] ? true : ($data['is_active'] ?? true);

            // Create first so the base-switch path is identical for a newly added
            // currency and an existing one.
            $currency = Currency::create([...$data, 'rate' => 1, 'is_base' => false]);
            $this->rates->remember($currency->id, $data['rate']);
            $currency->refresh();

            if ($data['is_base']) {
                $this->switchBase($currency, $request->user()?->id);
            } else {
                $this->recordRate($currency, 'created', $request->user()?->id);
            }

            return $currency->fresh();
        });

        return response()->json([
            'success' => true,
            'data' => new CurrencyResource($currency),
        ], 201);
    }

    public function show(Currency $currency)
    {
        return response()->json([
            'success' => true,
            'data' => new CurrencyResource($currency),
        ]);
    }

    public function update(Request $request, Currency $currency)
    {
        $data = $this->validated($request, $currency);
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        $currency = DB::transaction(function () use ($currency, $data, $request) {
            $currency = Currency::query()->lockForUpdate()->findOrFail($currency->id);

            if ($currency->is_base && array_key_exists('is_base', $data) && ! $data['is_base']) {
                throw ValidationException::withMessages([
                    'is_base' => ['Choose another currency as the base instead of unsetting the current base.'],
                ]);
            }

            if ($currency->is_base && array_key_exists('is_active', $data) && ! $data['is_active']) {
                throw ValidationException::withMessages([
                    'is_active' => ['The base currency must remain active.'],
                ]);
            }

            $switchingBase = ($data['is_base'] ?? false) && ! $currency->is_base;
            if ($switchingBase) {
                // Save editable fields, including the rate that determines the
                // rebase factor, before it becomes the new base at rate 1.
                $baseData = $data;
                unset($baseData['is_base'], $baseData['rate']);
                $currency->fill($baseData);
                $currency->save();
                if (isset($data['rate'])) {
                    $this->rates->remember($currency->id, $data['rate']);
                    $currency->refresh();
                }
                $this->switchBase($currency, $request->user()?->id);
            } else {
                if ($currency->is_base) {
                    $data['rate'] = 1.0;
                    $data['is_active'] = true;
                }

                $oldRate = (float) $currency->rate;
                $fields = $data;
                unset($fields['rate']);
                $currency->fill($fields);
                $currency->save();
                if (isset($data['rate'])) {
                    $this->rates->remember($currency->id, $data['rate']);
                    $currency->refresh();
                }

                if ((float) $currency->rate !== $oldRate) {
                    $this->recordRate($currency, 'rate_updated', $request->user()?->id);
                }
            }

            return $currency->fresh();
        });

        return response()->json([
            'success' => true,
            'data' => new CurrencyResource($currency),
        ]);
    }

    public function destroy(Currency $currency)
    {
        if ($currency->is_base) {
            return response()->json([
                'success' => false,
                'code' => 'business.base_currency_cannot_be_deleted',
            ], 400);
        }

        if ($currency->applicantContracts()->exists() || $currency->salaries()->exists() || $currency->incomes()->exists() || $currency->expenses()->exists()) {
            return response()->json([
                'success' => false,
                'code' => 'business.currency_in_use',
            ], 409);
        }

        $currency->delete();

        return response()->json([
            'success' => true,
            'message' => 'Currency deleted successfully',
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Currency $currency = null): array
    {
        return $request->validate([
            'name' => [
                $currency ? 'sometimes' : 'required',
                'string',
                'max:255',
                Rule::unique('currencies', 'name')->ignore($currency?->id),
            ],
            'code' => [
                $currency ? 'sometimes' : 'required',
                'string',
                'max:5',
                Rule::unique('currencies', 'code')->ignore($currency?->id),
            ],
            'symbol' => ['nullable', 'string', 'max:5'],
            'rate' => [$currency ? 'sometimes' : 'required', 'numeric', 'min:0.00000001'],
            'is_base' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'decimal_places' => ['sometimes', 'integer', 'between:0,6'],
        ]);
    }

    private function switchBase(Currency $target, ?int $userId): void
    {
        $currencies = Currency::query()->lockForUpdate()->orderBy('id')->get();
        $target = $currencies->firstWhere('id', $target->id) ?? $target;
        $oldBase = $currencies->firstWhere('is_base', true);
        $newBaseRate = (float) $target->rate;

        if ($newBaseRate <= 0) {
            throw ValidationException::withMessages([
                'rate' => ['business.currency_rate_invalid'],
            ]);
        }

        // Position salaries are configuration amounts, not historical payments.
        // Re-denominate their values before the target currency becomes rate 1.
        if ($oldBase && ! $oldBase->is($target)) {
            Position::query()->lockForUpdate()->get()->each(function (Position $position) use ($target, $newBaseRate): void {
                $sourceRate = (float) ($position->rate ?: 1);
                $position->fill([
                    'salary' => round(((float) $position->salary / $sourceRate) * $newBaseRate, $target->decimal_places),
                    'currency_id' => $target->id,
                    'currency_code' => $target->code,
                    'currency_symbol' => $target->symbol,
                    'currency_decimal_places' => $target->decimal_places,
                    'rate' => 1,
                ]);
                $position->save();
            });
        }

        foreach ($currencies as $currency) {
            $currency->is_base = $currency->is($target);
            $currency->is_active = $currency->is($target) ? true : $currency->is_active;
            $currency->rate = $currency->is($target)
                ? 1.0
                : round(((float) $currency->rate) / $newBaseRate, 8);
            $currency->save();
            $this->recordRate($currency, 'base_rebased', $userId, $target->id);
        }
    }

    private function recordRate(Currency $currency, string $reason, ?int $userId, ?int $baseCurrencyId = null): void
    {
        CurrencyRateHistory::create([
            'currency_id' => $currency->id,
            'base_currency_id' => $baseCurrencyId ?? Currency::query()->where('is_base', true)->value('id'),
            'rate' => $currency->rate,
            'reason' => $reason,
            'recorded_by' => $userId,
        ]);
    }
}
