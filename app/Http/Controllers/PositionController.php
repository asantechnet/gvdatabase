<?php

namespace App\Http\Controllers;

use App\Http\Resources\PositionResource;
use App\Models\Currency;
use App\Models\Position;
use App\Services\CurrencyRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PositionController extends Controller
{
    public function __construct(private CurrencyRateService $rates) {}

    public function index(): JsonResponse
    {
        $positions = Position::query()
            ->withCount(['employees', 'users'])
            ->orderBy('title')
            ->get();

        return response()->json([
            'success' => true,
            'data' => PositionResource::collection($positions),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255', Rule::unique('positions', 'title')->whereNull('deleted_at')],
            'salary' => 'required|numeric|min:0',
            'currency_id' => ['required', 'integer', Rule::exists('currencies', 'id')->where('is_active', true)],
            'rate' => 'required|numeric|gt:0',
            'status' => 'required|in:active,inactive',
            'description' => 'nullable|string',
        ]);

        $position = Position::create([...$data, ...$this->currencySnapshot($data)]);

        return response()->json([
            'success' => true,
            'data' => new PositionResource($position->loadCount(['employees', 'users'])),
        ], 201);
    }

    public function show($id)
    {
        $position = Position::withCount(['employees', 'users'])->find($id);
        if (! $position) {
            return response()->json([
                'success' => false,
                'message' => 'Position not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new PositionResource($position->loadCount(['employees', 'users'])),
        ]);
    }

    public function update(Request $request, $id)
    {
        $position = Position::find($id);
        if (! $position) {
            return response()->json([
                'success' => false,
                'message' => 'Position not found',
            ], 404);
        }

        if ($position->system_key && ($request->has('title') || $request->has('status'))) {
            return response()->json([
                'success' => false,
                'code' => 'business.system_position_locked',
            ], 422);
        }

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255', Rule::unique('positions', 'title')->whereNull('deleted_at')->ignore($position->id)],
            'salary' => 'sometimes|numeric|min:0',
            'currency_id' => ['required_with:rate', 'integer', Rule::exists('currencies', 'id')->where('is_active', true)],
            'rate' => 'required_with:currency_id|numeric|gt:0',
            'status' => 'sometimes|in:active,inactive',
            'description' => 'nullable|string',
        ]);

        $snapshot = array_key_exists('currency_id', $data)
            ? $this->currencySnapshot($data)
            : [];
        $position->update([...$data, ...$snapshot]);

        return response()->json([
            'success' => true,
            'data' => new PositionResource($position->loadCount(['employees', 'users'])),
        ]);
    }

    public function destroy($id)
    {
        $position = Position::find($id);
        if (! $position) {
            return response()->json([
                'success' => false,
                'message' => 'Position not found',
            ], 404);
        }

        if ($position->employees()->exists() || $position->users()->exists()) {
            return response()->json([
                'success' => false,
                'code' => 'business.position_in_use',
            ], 422);
        }

        if ($position->system_key) {
            return response()->json([
                'success' => false,
                'code' => 'business.system_position_cannot_be_deleted',
            ], 422);
        }

        $position->delete();

        return response()->json([
            'success' => true,
            'message' => 'Position deleted successfully',
        ]);
    }

    /**
     * Capture the selected currency beside a suggested salary. The rate is
     * written through the shared service so this selection is immediately
     * reflected anywhere the Finance currency options are used.
     */
    private function currencySnapshot(array $data): array
    {
        $currency = Currency::query()->find($data['currency_id']);
        if (! $currency) {
            throw ValidationException::withMessages(['currency_id' => ['validation.exists']]);
        }

        $rate = $this->rates->remember($currency->id, $data['rate']);
        if ($rate === null) {
            throw ValidationException::withMessages(['rate' => ['api.errors.invalid_currency_rate']]);
        }
        $currency->refresh();

        return [
            'currency_id' => $currency->id,
            'currency_code' => $currency->code,
            'currency_symbol' => $currency->symbol,
            'currency_decimal_places' => $currency->decimal_places,
            'rate' => $rate,
        ];
    }
}
