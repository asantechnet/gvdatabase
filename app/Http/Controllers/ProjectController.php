<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $projects = Project::query()->with(['services.currency', 'boards'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();

        return response()->json(['success' => true, 'data' => ProjectResource::collection($projects)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize($request, 'create_projects');
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        $services = $data['service_ids'] ?? [];
        unset($data['service_ids']);
        $project = Project::create($data);
        $project->services()->sync($services);

        return response()->json(['success' => true, 'data' => new ProjectResource($project->load(['services.currency', 'boards']))], 201);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        $this->authorize($request, 'view_projects');
        return response()->json(['success' => true, 'data' => new ProjectResource($project->load(['services.currency', 'boards.tasks']))]);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $this->authorize($request, 'edit_projects');
        $data = $this->validated($request, true);
        $services = $data['service_ids'] ?? null;
        unset($data['service_ids']);
        $project->update($data);
        if ($services !== null) $project->services()->sync($services);

        return response()->json(['success' => true, 'data' => new ProjectResource($project->fresh()->load(['services.currency', 'boards']))]);
    }

    public function destroy(Request $request, Project $project): JsonResponse
    {
        $this->authorize($request, 'delete_projects');
        $project->delete();

        return response()->json(['success' => true]);
    }

    private function authorize(Request $request, string $permission): void
    {
        abort_unless($request->user()->hasPermission($permission), 403, 'auth.forbidden');
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        return $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => [$partial ? 'sometimes' : 'nullable', 'in:active,on_hold,completed,archived'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'service_ids' => ['sometimes', 'array'],
            'service_ids.*' => ['integer', 'distinct', 'exists:services,id'],
        ]);
    }
}
