<?php

namespace App\Http\Controllers;

use App\Http\Resources\CompanyResource;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Company::query()->withCount('contracts');

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('legal_name', 'like', "%{$search}%")
                    ->orWhere('trade_name', 'like', "%{$search}%")
                    ->orWhere('registration_number', 'like', "%{$search}%")
                    ->orWhere('tax_number', 'like', "%{$search}%");
            });
        }

        if (! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        return response()->json([
            'success' => true,
            'data' => CompanyResource::collection($query->latest('id')->get()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $company = Company::create($data);

        return response()->json([
            'success' => true,
            'data' => new CompanyResource($company),
        ], 201);
    }

    public function show(Company $company): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new CompanyResource($company->loadCount('contracts')),
        ]);
    }

    public function update(Request $request, Company $company): JsonResponse
    {
        $data = $this->validated($request, partial: true);

        $company->update($data);

        return response()->json([
            'success' => true,
            'data' => new CompanyResource($company->fresh()),
        ]);
    }

    public function destroy(Company $company): JsonResponse
    {
        if ($company->contracts()->exists() || $company->outsourcedCustomerServices()->exists() || $company->outsourcingAccruals()->exists() || $company->outsourcingSettlements()->exists()) {
            // Historical contractual and financial references must stay renderable; deactivate instead.
            $company->update(['is_active' => false]);

            return response()->json([
                'success' => false,
                'message' => 'This company is referenced by existing contracts and has been deactivated instead of deleted.',
                'code' => 'business.company_in_use',
            ], 409);
        }

        $company->delete();

        return response()->json([
            'success' => true,
            'message' => 'Company deleted successfully.',
        ]);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $sometimes = $partial ? 'sometimes|' : '';

        $rules = [
            'legal_name' => $sometimes.'required|string|max:255',
            'trade_name' => 'nullable|string|max:255',
            'registration_number' => 'nullable|string|max:100',
            'tax_number' => 'nullable|string|max:100',
            'national_id' => 'nullable|string|max:100',
            'legal_form' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:100',
            'website' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:50',
            'address_line' => 'nullable|string|max:2000',
            'industry' => 'nullable|string|max:150',
            'description' => 'nullable|string|max:5000',
            'representative_name' => 'nullable|string|max:255',
            'representative_title' => 'nullable|string|max:150',
            'representative_phone' => 'nullable|string|max:100',
            'representative_email' => 'nullable|email|max:255',
            'is_active' => 'sometimes|boolean',
        ];

        return $request->validate($rules);
    }
}
