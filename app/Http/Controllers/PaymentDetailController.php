<?php

namespace App\Http\Controllers;

use App\Http\Resources\PaymentDetailResource;
use App\Models\PaymentDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentDetailController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => PaymentDetailResource::collection(PaymentDetail::query()->orderBy('method')->get()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $paymentDetail = PaymentDetail::create($this->validated($request));

        return response()->json([
            'success' => true,
            'data' => new PaymentDetailResource($paymentDetail),
        ], 201);
    }

    public function show(PaymentDetail $paymentDetail): JsonResponse
    {
        return response()->json(['success' => true, 'data' => new PaymentDetailResource($paymentDetail)]);
    }

    public function update(Request $request, PaymentDetail $paymentDetail): JsonResponse
    {
        $paymentDetail->update($this->validated($request, $paymentDetail));

        return response()->json([
            'success' => true,
            'data' => new PaymentDetailResource($paymentDetail->fresh()),
        ]);
    }

    public function destroy(PaymentDetail $paymentDetail): JsonResponse
    {
        $paymentDetail->delete();

        return response()->json(['success' => true, 'message' => 'Payment detail deleted.']);
    }

    private function validated(Request $request, ?PaymentDetail $paymentDetail = null): array
    {
        return $request->validate([
            'method' => [
                'required',
                'string',
                'max:255',
                Rule::unique('payment_details', 'method')->whereNull('deleted_at')->ignore($paymentDetail?->id),
            ],
            'instruction' => ['required', 'string', 'max:5000'],
        ]);
    }
}
