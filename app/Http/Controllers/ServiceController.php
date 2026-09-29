<?php

namespace App\Http\Controllers;

use App\Http\Resources\ServiceResource;
use App\Models\ContractTemplate;
use App\Models\Service;
use App\Models\ServiceVariant;
use App\Services\Contracts\ServiceContractGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ServiceController extends Controller
{
    public function __construct(private readonly ServiceContractGenerator $serviceContracts) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $services = Service::with(['currency', 'packages', 'contractTemplate', 'variants'])->orderBy('type')->get();

        return response()->json(ServiceResource::collection($services));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|max:255',
            'code' => 'nullable|string|max:80|unique:services,code',
            'category' => ['sometimes', 'string', Rule::in(['general', 'scholarship', 'test_offer'])],
            'description' => 'nullable|string|max:5000',
            'fee' => 'required|numeric|min:0',
            'status' => [
                'required',
                'string',
                Rule::in(Service::getValidStatuses()),
            ],
            'currency_id' => 'required|exists:currencies,id',
        ]);

        $service = Service::create($validated);

        return response()->json(new ServiceResource($service->load(['currency', 'packages', 'contractTemplate', 'variants'])), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Service $service): JsonResponse
    {
        return response()->json(new ServiceResource($service->load(['currency', 'packages', 'contractTemplate', 'variants'])));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Service $service): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'sometimes|required|string|max:255',
            'code' => ['nullable', 'string', 'max:80', Rule::unique('services', 'code')->ignore($service->id)],
            'category' => ['sometimes', 'string', Rule::in(['general', 'scholarship', 'test_offer'])],
            'description' => 'nullable|string|max:5000',
            'fee' => 'sometimes|required|numeric|min:0',
            'status' => [
                'sometimes',
                'required',
                'string',
                Rule::in(Service::getValidStatuses()),
            ],
            'currency_id' => 'sometimes|required|exists:currencies,id',
        ]);

        $service->update($validated);

        return response()->json(new ServiceResource($service->load(['currency', 'packages', 'contractTemplate', 'variants'])));
    }

    /**
     * Bind (or create) the contract template that represents this service.
     *
     * A service has exactly one contract template. Passing
     * `contract_template_id` attaches an existing, unbound template; omitting
     * it creates a draft template from the service metadata (or updates the
     * service's own template when one already exists).
     */
    public function bindContractTemplate(Request $request, Service $service): JsonResponse
    {
        $data = $request->validate([
            'contract_template_id' => ['nullable', 'integer', 'exists:contract_templates,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'cover_text' => ['nullable', 'string', 'max:2000'],
            'content' => ['nullable', 'string'],
        ]);

        if (! empty($data['contract_template_id'])) {
            $template = ContractTemplate::findOrFail($data['contract_template_id']);
            if ($template->service_id && (int) $template->service_id !== (int) $service->id) {
                throw ValidationException::withMessages([
                    'contract_template_id' => ['That contract template is already bound to another service.'],
                ]);
            }
            $template->update(['service_id' => $service->id, 'is_active' => true]);
        } else {
            $template = $this->serviceContracts->templateFor($service, $request->user()?->id);
            $template->update(array_filter([
                'name' => $data['name'] ?? null,
                'cover_text' => $data['cover_text'] ?? null,
                'content' => $data['content'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''));
        }

        return response()->json(new ServiceResource($service->fresh(['currency', 'packages', 'contractTemplate'])));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Service $service): JsonResponse
    {
        if ($service->invoices()->exists() || $service->contractServices()->exists() || $service->projects()->exists() || $service->packages()->exists()) {
            return response()->json([
                'message' => 'Assigned, projected, or invoiced services cannot be deleted. Mark the service inactive instead.',
            ], 409);
        }

        // A generated template is service-owned; detach it rather than deleting
        // it so any history that references it stays intact.
        $service->contractTemplates()->update(['service_id' => null, 'is_active' => false]);

        $service->delete();

        return response()->json(null, 204);
    }

    public function storeVariant(Request $request, Service $service): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:80', Rule::unique('service_variants', 'code')->where('service_id', $service->id)],
            'fee' => ['nullable', 'numeric', 'min:0'],
        ]);
        $variant = $service->variants()->create($data);
        return response()->json(['success' => true, 'data' => $variant], 201);
    }

    public function updateVariant(Request $request, Service $service, ServiceVariant $variant): JsonResponse
    {
        abort_unless($variant->service_id === $service->id, 404);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => ['sometimes', 'required', 'string', 'max:80', Rule::unique('service_variants', 'code')->where('service_id', $service->id)->ignore($variant->id)],
            'fee' => ['nullable', 'numeric', 'min:0'],
        ]);
        $variant->update($data);
        return response()->json(['success' => true, 'data' => $variant->fresh()]);
    }

    public function destroyVariant(Service $service, ServiceVariant $variant): JsonResponse
    {
        abort_unless($variant->service_id === $service->id, 404);
        abort_if($variant->contractServices()->exists(), 409, 'A variant used by a contract cannot be deleted.');
        $variant->delete();
        return response()->json(['success' => true]);
    }
}
