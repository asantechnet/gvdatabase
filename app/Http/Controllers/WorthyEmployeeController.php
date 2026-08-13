<?php

namespace App\Http\Controllers;

use App\Models\WorthyEmployee;
use App\Models\Employee;
use App\Models\Currency;
use App\Http\Resources\WorthyEmployeeResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WorthyEmployeeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return WorthyEmployeeResource::collection(
            WorthyEmployee::with('employee.user')->orderBy('bonus_month', 'desc')->orderBy('created_at', 'desc')->get()
        );
    }

    public function store(Request $request): WorthyEmployeeResource
    {
        $validated = $request->validate([
            'employeeId' => 'required|exists:employees,id',
            'performanceScore' => 'required|numeric|between:1.0,5.0',
            'eligibilityReason' => ['required', Rule::in([
                'Top Performer', 'Project Milestone', 'Client Praise', 'Work Anniversary', 'Manager Nomination'
            ])],
            'achievementDetails' => 'required|string',
            'suggestedAmount' => 'required|numeric|min:0',
            'currencyId' => ['required', 'integer', Rule::exists('currencies', 'id')->where('is_active', true)],
            'selectedAmount' => 'nullable|numeric|min:0',
            'selectedCurrencyId' => ['nullable', 'integer', Rule::exists('currencies', 'id')->where('is_active', true)],
            'selectedType' => ['required', Rule::in([
                'Performance', 'Spot Bonus', 'Project Completion', 'Retention'
            ])],
            'bonusMonth' => 'sometimes|date_format:Y-m|before_or_equal:now',
        ]);

        if (! Employee::selectable()->whereKey($validated['employeeId'])->exists()) {
            throw ValidationException::withMessages([
                'employeeId' => ['business.employee_not_selectable'],
            ]);
        }

        $bonusMonth = ($validated['bonusMonth'] ?? now()->format('Y-m')) . '-01';

        $originalCurrency = Currency::findOrFail($validated['currencyId']);
        $selectedCurrency = Currency::findOrFail($validated['selectedCurrencyId'] ?? $validated['currencyId']);
        $originalRate = $this->validRate($originalCurrency);
        $selectedRate = $this->validRate($selectedCurrency);
        $suggestedAmount = (float) $validated['suggestedAmount'];
        $selectedAmount = (float) ($validated['selectedAmount'] ?? $suggestedAmount);

        $worthyEmployee = WorthyEmployee::create([
            'employee_id' => $validated['employeeId'],
            ...$this->currencySnapshot($originalCurrency),
            'performance_score' => $validated['performanceScore'],
            'eligibility_reason' => $validated['eligibilityReason'],
            'achievement_details' => $validated['achievementDetails'],
            'suggested_amount' => $suggestedAmount,
            'suggested_rate_to_base' => $originalRate,
            'suggested_base_amount' => round($suggestedAmount * $originalRate, 4),
            'selected_amount' => $selectedAmount,
            ...$this->currencySnapshot($selectedCurrency, 'selected_'),
            'selected_rate_to_base' => $selectedRate,
            'selected_base_amount' => round($selectedAmount * $selectedRate, 4),
            'selected_type' => $validated['selectedType'],
            'is_granted' => false,
            'bonus_month' => $bonusMonth,
            'currency_snapshot_source' => 'recorded_at_creation',
        ]);

        return new WorthyEmployeeResource($worthyEmployee->load('employee.user'));
    }

    public function show(WorthyEmployee $worthyEmployee): WorthyEmployeeResource
    {
        return new WorthyEmployeeResource($worthyEmployee->load('employee.user'));
    }

    public function update(Request $request, WorthyEmployee $worthyEmployee): WorthyEmployeeResource
    {
        abort_unless(
            $request->user()->hasPermission($request->has('isGranted') ? 'grant_bonuses' : 'edit_bonus_nominations'),
            403,
            'auth.forbidden',
        );

        $validated = $request->validate([
            'selectedAmount' => 'sometimes|required|numeric|min:0',
            'selectedCurrencyId' => ['sometimes', 'required', 'integer', Rule::exists('currencies', 'id')->where('is_active', true)],
            'selectedType' => ['sometimes', 'required', Rule::in([
                'Performance', 'Spot Bonus', 'Project Completion', 'Retention'
            ])],
            'isGranted' => 'sometimes|required|boolean',
        ]);

        abort_if($worthyEmployee->is_granted && $validated !== [], 422, 'business.granted_bonus_immutable');
        abort_if($worthyEmployee->salaries()->exists(), 422, 'business.bonus_already_in_salary');

        $selectedCurrency = isset($validated['selectedCurrencyId'])
            ? Currency::findOrFail($validated['selectedCurrencyId'])
            : Currency::findOrFail($worthyEmployee->selected_currency_id);
        $selectedRate = $this->validRate($selectedCurrency);
        $selectedAmount = (float) ($validated['selectedAmount'] ?? $worthyEmployee->selected_amount);
        if (isset($validated['selectedAmount'])) {
            $worthyEmployee->selected_amount = $selectedAmount;
        }
        if (isset($validated['selectedCurrencyId'])) $worthyEmployee->fill($this->currencySnapshot($selectedCurrency, 'selected_'));
        if (isset($validated['selectedAmount']) || isset($validated['selectedCurrencyId'])) {
            $worthyEmployee->selected_rate_to_base = $selectedRate;
            $worthyEmployee->selected_base_amount = round($selectedAmount * $selectedRate, 4);
        }
        if (isset($validated['selectedType'])) {
            $worthyEmployee->selected_type = $validated['selectedType'];
        }
        if (isset($validated['isGranted'])) {
            abort_unless($validated['isGranted'] === true, 422, 'business.granted_bonus_cannot_be_reversed');
            $grantCurrency = Currency::findOrFail($worthyEmployee->selected_currency_id);
            $grantRate = $this->validRate($grantCurrency);
            $worthyEmployee->is_granted = true;
            $worthyEmployee->granted_amount = $worthyEmployee->selected_amount;
            $worthyEmployee->fill($this->currencySnapshot($grantCurrency, 'granted_'));
            $worthyEmployee->granted_rate_to_base = $grantRate;
            $worthyEmployee->granted_base_amount = round((float) $worthyEmployee->selected_amount * $grantRate, 4);
            $worthyEmployee->granted_at = now('UTC');
            $worthyEmployee->granted_by = $request->user()->id;
        }

        $worthyEmployee->save();

        return new WorthyEmployeeResource($worthyEmployee->fresh()->load('employee.user'));
    }

    public function destroy(WorthyEmployee $worthyEmployee): JsonResponse
    {
        abort_if($worthyEmployee->is_granted || $worthyEmployee->salaries()->exists(), 422, 'business.granted_bonus_immutable');
        $worthyEmployee->delete();

        return response()->json([
            'message' => 'Candidate successfully dismissed.'
        ]);
    }

    private function currencySnapshot(Currency $currency, string $prefix = ''): array
    {
        return [
            $prefix.'currency_id' => $currency->id,
            $prefix.'currency_code' => $currency->code,
            $prefix.'currency_name' => $currency->name,
            $prefix.'currency_symbol' => $currency->symbol,
            $prefix.'currency_decimal_places' => $currency->decimal_places,
        ];
    }

    private function validRate(Currency $currency): float
    {
        $rate = (float) $currency->rate_against_base;
        abort_unless($rate > 0, 422, 'business.currency_rate_invalid');

        return $rate;
    }
}
