<?php

namespace App\Http\Controllers;

use App\Http\Resources\FinanceTypeResource;
use App\Models\FinanceType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FinanceTypeController extends Controller
{
    public function index(string $kind)
    {
        return FinanceTypeResource::collection(FinanceType::where('kind', $kind)->orderBy('id')->get());
    }

    public function store(Request $request, string $kind)
    {
        if (is_string($request->input('name'))) {
            $request->merge(['name' => trim($request->input('name'))]);
        }
        $data = $request->validate(['name' => ['required', 'string', 'max:255', Rule::unique('finance_types', 'name')->where('kind', $kind), Rule::unique('finance_types', 'value')->where('kind', $kind)]]);
        $type = FinanceType::create(['kind' => $kind, 'value' => $data['name'], 'name' => $data['name']]);

        return (new FinanceTypeResource($type))->response()->setStatusCode(201);
    }

    public function show(string $kind, int $id)
    {
        return new FinanceTypeResource(FinanceType::where('kind', $kind)->findOrFail($id));
    }

    public function update(Request $request, string $kind, int $id)
    {
        $type = FinanceType::where('kind', $kind)->findOrFail($id);
        if (is_string($request->input('name'))) {
            $request->merge(['name' => trim($request->input('name'))]);
        }
        $data = $request->validate(['name' => ['required', 'string', 'max:255', Rule::unique('finance_types', 'name')->where('kind', $kind)->ignore($id)]]);
        // Keep stored transaction values and issued document snapshots stable.
        $type->update($data);

        return new FinanceTypeResource($type);
    }

    public function destroy(string $kind, int $id)
    {
        $type = FinanceType::where('kind', $kind)->findOrFail($id);
        if ($type->is_system || DB::table($kind === 'income' ? 'incomes' : 'expenses')->where('type', $type->value)->exists()) {
            throw ValidationException::withMessages(['name' => ['business.finance_type_in_use']]);
        }
        $type->delete();

        return response()->noContent();
    }
}
