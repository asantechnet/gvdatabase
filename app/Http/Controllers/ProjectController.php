<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProjectResource;
use App\Models\ContractService;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $projects = Project::query()->with($this->projectRelations())
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
        $syncServices = array_key_exists('service_ids', $data);
        $serviceIds = $this->serviceIds($data['service_ids'] ?? []);
        unset($data['service_ids']);

        $project = DB::transaction(function () use ($data, $syncServices, $serviceIds): Project {
            $project = Project::create($data);

            if ($syncServices) {
                $this->assertServicesBelongToApplicant($project, $serviceIds);
                $project->services()->sync($serviceIds);
            }

            return $project;
        });

        return response()->json(['success' => true, 'data' => new ProjectResource($project->load($this->projectRelations()))], 201);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        $this->authorize($request, 'view_projects');

        return response()->json(['success' => true, 'data' => new ProjectResource($project->load($this->projectRelations()))]);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $this->authorize($request, 'edit_projects');
        $data = $this->validated($request, true);
        $syncServices = array_key_exists('service_ids', $data);
        $serviceIds = $this->serviceIds($data['service_ids'] ?? []);
        $applicantChanged = array_key_exists('applicant_id', $data) && (int) $data['applicant_id'] !== (int) $project->applicant_id;
        unset($data['service_ids']);

        $project = DB::transaction(function () use ($project, $data, $syncServices, $serviceIds, $applicantChanged): Project {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            $nextStatus = $data['status'] ?? $project->status;
            if ($nextStatus === 'completed') {
                abort_if($project->boards()->whereHas('tasks', fn ($query) => $query->where('progress', '<', 100))->exists(), 422, 'business.project_has_incomplete_tasks');
            }
            if ($nextStatus === 'archived') {
                abort_unless($project->status === 'completed' || ($data['status'] ?? null) === 'completed', 422, 'business.project_must_be_completed_before_archive');
            }
            $project->update($data);

            if ($syncServices) {
                $this->assertServicesBelongToApplicant($project, $serviceIds);
                $project->services()->sync($serviceIds);
            } elseif ($applicantChanged) {
                // Moving a project must not leave it pointing at services the
                // new applicant does not have. The caller can send an explicit
                // service_ids array in the same request to replace them.
                $this->assertServicesBelongToApplicant(
                    $project,
                    $project->services()->pluck('services.id')->map(fn ($id) => (int) $id)->all(),
                );
            }

            return $project;
        });

        return response()->json(['success' => true, 'data' => new ProjectResource($project->fresh()->load($this->projectRelations()))]);
    }

    public function destroy(Request $request, Project $project): JsonResponse
    {
        $this->authorize($request, 'delete_projects');
        abort_if($project->boards()->exists() || $project->expenses()->exists(), 422, 'business.project_has_operational_history');
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
            'applicant_id' => [$required, 'integer', 'exists:applicants,id'],
            'service_ids' => ['sometimes', 'array'],
            'service_ids.*' => ['required', 'integer', 'distinct', 'exists:services,id'],
        ]);
    }

    /** @return list<string> */
    private function projectRelations(): array
    {
        return ['applicant', 'services.currency', 'boards.tasks'];
    }

    /** @param array<int, int|string> $serviceIds @return list<int> */
    private function serviceIds(array $serviceIds): array
    {
        return array_values(array_map(static fn ($id) => (int) $id, $serviceIds));
    }

    /**
     * A project is a delivery plan for the services this specific applicant has
     * purchased. Keeping that check here protects the relation regardless of
     * whether it is edited from the applicant page or the project workspace.
     *
     * @param  list<int>  $serviceIds
     */
    private function assertServicesBelongToApplicant(Project $project, array $serviceIds): void
    {
        if ($serviceIds === []) {
            return;
        }

        $availableIds = ContractService::query()
            ->whereHas('contract', fn ($contracts) => $contracts->where('applicant_id', $project->applicant_id))
            ->whereIn('service_id', $serviceIds)
            ->whereNull('deleted_at')
            ->pluck('service_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (array_diff($serviceIds, $availableIds) !== []) {
            throw ValidationException::withMessages([
                'service_ids' => ['Each selected service must be assigned to the project applicant.'],
            ]);
        }
    }
}
