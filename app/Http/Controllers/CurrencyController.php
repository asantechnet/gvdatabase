<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Http\Resources\CurrencyResource;
use Illuminate\Http\Request;
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
            'rate_to_base' => 'required|numeric|min:0',
            'is_base' => 'boolean',
            'is_active' => 'boolean',
            'decimal_places' => 'integer|min:0|max:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $data['rate_against_base'] = $data['rate_to_base'];
        unset($data['rate_to_base']);

        if ($data['is_base'] ?? false) {
            $this->setAsBaseCurrency($data);
        }

        $currency = Currency::create($data);
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
            'rate_to_base' => 'sometimes|numeric|min:0',
            'is_base' => 'boolean',
            'is_active' => 'boolean',
            'decimal_places' => 'integer|min:0|max:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        if (isset($data['rate_to_base'])) {
            $data['rate_against_base'] = $data['rate_to_base'];
            unset($data['rate_to_base']);
        }

        if (($data['is_base'] ?? false) && !$currency->is_base) {
            $this->setAsBaseCurrency($data, $currency);
        }

        $currency->update($data);
        return response()->json([
            'success' => true,
            'data' => new CurrencyResource($currency)
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
                'message' => 'Cannot delete base currency'
            ], 400);
        }

        if ($currency->invoices()->exists() || $currency->services()->exists() || $currency->salaries()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Currencies referenced by services, invoices, or salaries cannot be deleted.',
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
        $newBaseRate = (float) ($currentCurrency?->rate_against_base ?? $data['rate_against_base'] ?? 1);
        $otherCurrencies = Currency::query()
            ->when($currentCurrency, fn ($query) => $query->where('id', '!=', $currentCurrency->id))
            ->get();

        Currency::query()
            ->when($currentCurrency, fn ($query) => $query->where('id', '!=', $currentCurrency->id))
            ->update(['is_base' => false]);

        // Set this currency as base
        $data['is_base'] = true;
        $data['rate_against_base'] = 1.0;
        $data['is_active'] = true;

        if ($newBaseRate > 0) {
            foreach ($otherCurrencies as $currency) {
                $currency->rate_against_base = $currency->rate_against_base / $newBaseRate;
                $currency->save();
            }
        }
    }
}
