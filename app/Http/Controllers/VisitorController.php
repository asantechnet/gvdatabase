<?php

namespace App\Http\Controllers;

use App\Models\Visitor;
use App\Http\Resources\VisitorResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;

class VisitorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = $request->input('search');
        $company = $request->input('company');
        $marketer = $request->input('marketer');

        $query = Visitor::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('company', 'like', "%$search%")
                    ->orWhere('contact', 'like', "%$search%");
            });
        }

        if ($company) {
            $query->where('company', $company);
        }

        if ($marketer) {
            $query->where('marketer', $marketer);
        }

        return VisitorResource::collection($query->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): VisitorResource
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company' => 'required|string|max:255',
            'contact' => 'required|string|max:255',
            'service' => 'required|string|max:255',
            'marketer' => 'required|string|max:255',
            'date' => 'required|date',
        ]);

        $visitor = Visitor::create($validated);

        return new VisitorResource($visitor);
    }

    /**
     * Display the specified resource.
     */
    public function show(Visitor $visitor): VisitorResource
    {
        return new VisitorResource($visitor);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Visitor $visitor): VisitorResource
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'company' => 'sometimes|required|string|max:255',
            'contact' => 'sometimes|required|string|max:255',
            'service' => 'sometimes|required|string|max:255',
            'marketer' => 'sometimes|required|string|max:255',
            'date' => 'sometimes|required|date',
        ]);

        $visitor->update($validated);

        return new VisitorResource($visitor);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Visitor $visitor): JsonResponse
    {
        $visitor->delete();

        return response()->json(null, 204);
    }
}
