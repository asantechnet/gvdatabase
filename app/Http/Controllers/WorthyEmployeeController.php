<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PaginatesLists;
use App\Http\Resources\WorthyEmployeeResource;
use App\Models\Employee;
use App\Models\WorthyEmployee;
use App\Services\CurrencyConversionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WorthyEmployeeController extends Controller
{
    use PaginatesLists;
    private const BONUS_CATEGORIES = ['Performance', 'Customer Appreciation', 'Task Completion', 'Work Anniversary', 'Manager Recognition'];

    public function __construct(private readonly CurrencyConversionService $currencies) {}

    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $query = WorthyEmployee::with(['employee.user', 'currency'])->latest('bonus_month')->latest('id');

        if ($this->requestedPerPage($request)) {
            return response()->json(['success' => true]
                + $this->paginatedPayload($request, $query, fn ($rows) => WorthyEmployeeResource::collection($rows)->resolve()));
        }

        return WorthyEmployeeResource::collection($query->get());
    }

    public function show(WorthyEmployee $worthyEmployee): WorthyEmployeeResource
    {
        return new WorthyEmployeeResource($worthyEmployee->load(['employee.user', 'currency']));
    }

    public function store(Request $request): WorthyEmployeeResource
    {
        $data = $request->validate([
            'employeeId' => 'required|exists:employees,id', 'performanceScore' => 'nullable|numeric|between:1,5',
            'achievementDetails' => 'required|string', 'amount' => 'nullable|numeric|min:0', 'suggestedAmount' => 'nullable|numeric|min:0', 'selectedAmount' => 'nullable|numeric|min:0',
            'bonusCategory' => ['nullable', Rule::in(self::BONUS_CATEGORIES)], 'selectedType' => ['nullable', Rule::in(self::BONUS_CATEGORIES)], 'bonusMonth' => 'sometimes|date_format:Y-m|before_or_equal:now',
        ]);
        if (! Employee::selectable()->whereKey($data['employeeId'])->exists()) {
            throw ValidationException::withMessages(['employeeId' => ['business.employee_not_selectable']]);
        }
        $category = $data['bonusCategory'] ?? $data['selectedType'] ?? null;
        if (! $category) {
            throw ValidationException::withMessages(['bonusCategory' => ['Select a bonus category.']]);
        }
        $suggested = (float) ($data['amount'] ?? $data['suggestedAmount'] ?? $data['selectedAmount'] ?? 0);
        $base = $this->currencies->currentBase();
        $selected = (float) ($data['amount'] ?? $data['selectedAmount'] ?? $suggested);
        $row = WorthyEmployee::create([
            'employee_id' => $data['employeeId'], 'performance_score' => $data['performanceScore'] ?? 3,
            'achievement_details' => $data['achievementDetails'],
            'suggested_amount' => $suggested, 'selected_amount' => $selected, 'selected_type' => $category,
            'is_granted' => false, 'bonus_month' => ($data['bonusMonth'] ?? now()->format('Y-m')).'-01',
            'currency_id' => $base->id, 'base_currency_id' => $base->id, 'currency_code' => $base->code,
            'currency_name' => $base->name, 'currency_symbol' => $base->symbol, 'currency_decimal_places' => $base->decimal_places,
            'base_currency_code' => $base->code, 'rate' => 1, 'base_amount' => $selected,
            'currency_snapshot_source' => 'recorded_at_creation',
        ]);

        return new WorthyEmployeeResource($row->load(['employee.user', 'currency']));
    }

    public function update(Request $request, WorthyEmployee $worthyEmployee): WorthyEmployeeResource
    {
        abort_unless($request->user()->hasPermission($request->has('isGranted') ? 'grant_bonuses' : 'edit_bonus_nominations'), 403, 'auth.forbidden');
        $data = $request->validate([
            'amount' => 'sometimes|required|numeric|min:0',
            'selectedAmount' => 'sometimes|required|numeric|min:0',
            'grantedAmount' => 'sometimes|required|numeric|min:0',
            'bonusCategory' => ['sometimes', 'required', Rule::in(self::BONUS_CATEGORIES)],
            'selectedType' => ['sometimes', 'required', Rule::in(self::BONUS_CATEGORIES)],
            'isGranted' => 'sometimes|required|boolean',
        ]);
        DB::transaction(function () use ($request, $worthyEmployee, $data): void {
            $worthyEmployee = WorthyEmployee::query()->lockForUpdate()->findOrFail($worthyEmployee->id);
            abort_if($worthyEmployee->is_granted && $data !== [], 422, 'business.granted_bonus_immutable');
            abort_if($worthyEmployee->salaries()->exists(), 422, 'business.bonus_already_in_salary');
            if (isset($data['amount']) || isset($data['selectedAmount'])) {
                $worthyEmployee->selected_amount = $data['amount'] ?? $data['selectedAmount'];
            }
            if (isset($data['bonusCategory']) || isset($data['selectedType'])) {
                $worthyEmployee->selected_type = $data['bonusCategory'] ?? $data['selectedType'];
            }
            if (($data['isGranted'] ?? false) === true) {
                // One resolved amount: the explicit grant payload wins, then the
                // selected nomination. A grant is never allowed to invent or
                // persist zero/negative money.
                $grantAmount = (float) ($data['grantedAmount'] ?? $data['selectedAmount'] ?? $data['amount'] ?? $worthyEmployee->selected_amount);
                if ($grantAmount <= 0) {
                    throw ValidationException::withMessages([
                        'grantedAmount' => ['business.bonus_amount_required'],
                    ]);
                }
                $worthyEmployee->selected_amount = $grantAmount;
                $worthyEmployee->is_granted = true;
                $worthyEmployee->granted_amount = $grantAmount;
                $worthyEmployee->base_amount = round($grantAmount / max((float) ($worthyEmployee->rate ?: 1), 0.00000001), 4);
                $worthyEmployee->granted_at = now('UTC');
                $worthyEmployee->granted_by = $request->user()->id;
            }
            $worthyEmployee->save();
        });

        return new WorthyEmployeeResource($worthyEmployee->fresh()->load(['employee.user', 'currency']));
    }

    public function destroy(WorthyEmployee $worthyEmployee): JsonResponse
    {
        DB::transaction(function () use ($worthyEmployee): void {
            $worthyEmployee = WorthyEmployee::query()->lockForUpdate()->findOrFail($worthyEmployee->id);
            abort_if($worthyEmployee->is_granted || $worthyEmployee->salaries()->exists(), 422, 'business.granted_bonus_immutable');
            $worthyEmployee->delete();
        });

        return response()->json(['message' => 'Candidate successfully dismissed.']);
    }
}
