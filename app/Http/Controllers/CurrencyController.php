<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Http\Resources\CurrencyResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CurrencyController extends Controller
{
    public function index()
    {
        $currencies = Currency::all();
        return response()->json([
            'success' => true,
            'data' => CurrencyResource::collection($currencies)
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:currencies,name',
            'code' => 'required|string|max:5|unique:currencies,code',
            'symbol' => 'nullable|string|max:5',
            'rate' => 'required|numeric|gt:0',
            'is_base' => 'boolean',
            'is_active' => 'boolean',
            'decimal_places' => 'integer|min:0|max:6',
        ]);

        $validator->validate();

        $data = $validator->validated();

        $currency = DB::transaction(function () use ($data) {
            if ($data['is_base'] ?? false) {
                $this->setAsBaseCurrency($data);
            }

            return Currency::create($data);
        });
        return response()->json([
            'success' => true,
            'data' => new CurrencyResource($currency)
        ], 201);
    }

    public function show($id)
    {
        $currency = Currency::find($id);
        if (!$currency) {
            return response()->json([
                'success' => false,
                'message' => 'Currency not found'
            ], 404);
        }
        return response()->json([
            'success' => true,
            'data' => new CurrencyResource($currency)
        ]);
    }

    public function update(Request $request, $id)
    {
        $currency = Currency::find($id);
        if (!$currency) {
            return response()->json([
                'success' => false,
                'message' => 'Currency not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('currencies', 'name')->ignore($id)
            ],
            'code' => [
                'sometimes',
                'string',
                'max:5',
                Rule::unique('currencies', 'code')->ignore($id)
            ],
            'symbol' => 'nullable|string|max:5',
            'rate' => 'sometimes|numeric|gt:0',
            'is_base' => 'boolean',
            'is_active' => 'boolean',
            'decimal_places' => 'integer|min:0|max:6',
        ]);

        $validator->validate();

        $data = $validator->validated();

        DB::transaction(function () use ($currency, $data) {
            if (($data['is_base'] ?? false) && ! $currency->is_base) {
                $this->setAsBaseCurrency($data, $currency);
            }

            $currency->update($data);
        });
        return response()->json([
            'success' => true,
            'data' => new CurrencyResource($currency->fresh())
        ]);
    }

    public function destroy($id)
    {
        $currency = Currency::find($id);
        if (!$currency) {
            return response()->json([
                'success' => false,
                'message' => 'Currency not found'
            ], 404);
        }

        if ($currency->is_base) {
            return response()->json([
                'success' => false,
                'code' => 'business.base_currency_cannot_be_deleted'
            ], 400);
        }

        if ($currency->invoices()->exists() || $currency->services()->exists() || $currency->salaries()->exists() || $currency->incomes()->exists() || $currency->expenses()->exists()) {
            return response()->json([
                'success' => false,
                'code' => 'business.currency_in_use',
            ], 409);
        }

        $currency->delete();
        return response()->json([
            'success' => true,
            'message' => 'Currency deleted successfully'
        ]);
    }

    protected function setAsBaseCurrency(&$data, $currentCurrency = null)
    {
        $newBaseRate = (float) ($currentCurrency?->rate ?? $data['rate'] ?? 1);
        $otherCurrencies = Currency::query()
            ->when($currentCurrency, fn ($query) => $query->where('id', '!=', $currentCurrency->id))
            ->get();

        Currency::query()
            ->when($currentCurrency, fn ($query) => $query->where('id', '!=', $currentCurrency->id))
            ->update(['is_base' => false]);

        // Set this currency as base
        $data['is_base'] = true;
        $data['rate'] = 1.0;
        $data['is_active'] = true;

        if ($newBaseRate > 0) {
            foreach ($otherCurrencies as $currency) {
                $currency->rate = $currency->rate / $newBaseRate;
                $currency->save();
            }
        }
    }
}
