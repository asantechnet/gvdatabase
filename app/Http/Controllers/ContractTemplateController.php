<?php

namespace App\Http\Controllers;

use App\Http\Resources\ContractTemplateResource;
use App\Models\ContractTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;

class ContractTemplateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $templates = ContractTemplate::latest()->get();
        return response()->json([
            'success' => true,
            'data' => ContractTemplateResource::collection($templates)
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:Employment,Service,Partnership,Lease,NDA',
            'cover_text' => 'nullable|string',
            'content' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $template = ContractTemplate::create($request->all());

        return response()->json([
            'success' => true,
            'data' => new ContractTemplateResource($template)
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $template = ContractTemplate::find($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Contract template not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new ContractTemplateResource($template)
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $template = ContractTemplate::find($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Contract template not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|string|in:Employment,Service,Partnership,Lease,NDA',
            'cover_text' => 'nullable|string',
            'content' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $template->update($request->all());

        return response()->json([
            'success' => true,
            'data' => new ContractTemplateResource($template)
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $template = ContractTemplate::find($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Contract template not found'
            ], 404);
        }

        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contract template deleted successfully'
        ]);
    }
}
