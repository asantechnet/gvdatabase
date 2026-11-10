<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAcademyCertificateRequest;
use App\Http\Resources\AcademyCertificateResource;
use App\Models\AcademyCertificate;
use App\Models\AcademyCertificateTemplate;
use App\Models\AcademyEnrollment;
use App\Services\AcademyCertificateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class AcademyCertificateController extends Controller
{
    public function __construct(private readonly AcademyCertificateService $service) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:issued,revoked'],
            'class_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $page = AcademyCertificate::with(['issuer', 'revoker'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['class_id'] ?? null, fn ($query, $classId) => $query->where('class_id', $classId))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($query) => $query
                ->where('certificate_number', 'like', "%{$search}%")
                ->orWhere('snapshot->award->student_name', 'like', "%{$search}%")))
            ->latest('issued_at')->latest('id')->paginate(30)->withQueryString();

        return response()->json([
            'success' => true,
            'data' => AcademyCertificateResource::collection($page->getCollection())->resolve($request),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    public function show(Request $request, AcademyCertificate $academyCertificate): JsonResponse
    {
        return response()->json(['success' => true, 'data' => new AcademyCertificateResource($academyCertificate->load(['issuer', 'revoker']))]);
    }

    public function store(StoreAcademyCertificateRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (! empty($data['class_id'])) {
            $result = $this->service->bulkIssue((int) $data['class_id'], $data['enrollment_ids'], $data['template_id'] ?? null, $request->user());

            return response()->json(['success' => true, 'data' => [
                'created' => $result['created'],
                'skipped' => $result['skipped'],
                'certificates' => AcademyCertificateResource::collection($result['certificates'])->resolve($request),
            ]], 201);
        }

        $enrollment = AcademyEnrollment::findOrFail($data['academy_enrollment_id']);
        $certificate = $this->service->issue($enrollment, $data['template_id'] ?? null, $request->user());

        return response()->json(['success' => true, 'data' => new AcademyCertificateResource($certificate->load(['issuer', 'revoker']))], 201);
    }

    public function revoke(Request $request, AcademyCertificate $academyCertificate): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('issue_academy_certificates'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $certificate = $this->service->revoke($academyCertificate, $data['reason'], $request->user());

        return response()->json(['success' => true, 'data' => new AcademyCertificateResource($certificate->load(['issuer', 'revoker']))]);
    }

    public function templates(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => AcademyCertificateTemplate::query()->orderByDesc('is_active')->orderBy('id')->get()
            ->map(fn (AcademyCertificateTemplate $template) => $this->templatePayload($template))->all()]);
    }

    public function updateTemplate(Request $request, AcademyCertificateTemplate $template): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('issue_academy_certificates'), 403);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'body' => ['sometimes', 'required', 'string', 'max:5000'],
            'signatory_title' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        if (isset($data['body'])) {
            AcademyCertificateTemplate::assertTokens($data['body']);
        }
        $template->update($data);

        return response()->json(['success' => true, 'data' => $this->templatePayload($template->fresh())]);
    }

    /** @return array<string, mixed> */
    private function templatePayload(AcademyCertificateTemplate $template): array
    {
        return [
            ...Arr::only($template->toArray(), ['id', 'name', 'title', 'body', 'signatory_title', 'is_active', 'updated_at']),
            'tokens' => AcademyCertificateTemplate::TOKENS,
        ];
    }
}
