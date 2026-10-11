<?php

namespace App\Http\Controllers;

use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use App\Services\Contracts\ContentSanitizer;
use App\Services\Contracts\PlaceholderRegistry;
use App\Services\ContractTemplateVersionNotificationService;
use App\Support\BuiltInService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContractTemplateVersionController extends Controller
{
    public function __construct(private readonly ContentSanitizer $sanitizer, private readonly ContractTemplateVersionNotificationService $notifications) {}

    public function index(ContractTemplate $template)
    {
        return response()->json(['success' => true, 'data' => $template->versions()->with(['language', 'country', 'test', 'creator', 'activator', 'archiver'])->withCount('contracts')->orderByDesc('version_number')->get()]);
    }

    public function store(Request $request, ContractTemplate $template)
    {
        $data = $request->validate([
            'country_id' => ['nullable', 'exists:countries,id'],
            'test_id' => ['nullable', 'exists:tests,id'],
            'language_id' => ['required', 'exists:languages,id'],
            'name' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
        ]);
        $data = $this->normalizeScope($template, $data);
        $data['content'] = $this->sanitizeContent($data['content'], $template->contractType?->slug);

        return DB::transaction(function () use ($data, $request, $template) {
            // Version numbers stay continuous inside one (country|test, language)
            // scope so each scope can independently go from v1 upwards.
            $scope = $template->versions()
                ->where('language_id', $data['language_id'])
                ->where('country_id', $data['country_id'])
                ->where('test_id', $data['test_id']);
            $number = (int) $scope->lockForUpdate()->max('version_number') + 1;
            $version = $template->versions()->create([...$data, 'version_number' => $number, 'status' => 'draft', 'is_current' => false, 'created_by' => $request->user()?->id]);
            $this->notifications->send('created', $version);
            return response()->json(['success' => true, 'data' => $version->load(['language', 'country', 'test', 'creator'])], 201);
        });
    }

    /**
     * A test-offer version is scoped by its Test; every other service is scoped
     * by its destination Country. Reject an ambiguous or missing scope instead
     * of silently issuing a version that can never be matched to a contract.
     */
    private function normalizeScope(ContractTemplate $template, array $data): array
    {
        if ($template->service === BuiltInService::TEST_OFFER) {
            abort_if(empty($data['test_id']) || ! empty($data['country_id']), 422, 'business.contract_version_scope_required');
            $data['country_id'] = null;
        } else {
            abort_if(empty($data['country_id']) || ! empty($data['test_id']), 422, 'business.contract_version_scope_required');
            $data['test_id'] = null;
        }

        return $data;
    }

    public function show(ContractTemplate $template, ContractTemplateVersion $version)
    {
        abort_unless($version->contract_template_id === $template->id, 404);
        return response()->json(['success' => true, 'data' => $version->load(['language', 'country', 'test', 'creator', 'activator', 'archiver'])->loadCount('contracts')]);
    }

    public function update(Request $request, ContractTemplate $template, ContractTemplateVersion $version)
    {
        abort_unless($version->contract_template_id === $template->id, 404);
        abort_unless($version->status === 'draft' && ! $version->is_current, 422, 'business.contract_template_version_locked');
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'content' => ['sometimes', 'required', 'string'],
        ]);
        if (array_key_exists('content', $data)) {
            $data['content'] = $this->sanitizeContent($data['content'], $template->contractType?->slug);
        }
        $version->update($data);
        $this->notifications->send('updated', $version);
        return response()->json(['success' => true, 'data' => $version->fresh(['language', 'country', 'test', 'creator'])]);
    }

    public function activate(ContractTemplate $template, ContractTemplateVersion $version)
    {
        abort_unless($version->contract_template_id === $template->id, 404);
        abort_unless($version->status === 'draft', 422, 'business.contract_template_version_not_draft');
        DB::transaction(function () use ($template, $version) {
            $template->versions()->where('language_id', $version->language_id)->where('country_id', $version->country_id)
                ->where('test_id', $version->test_id)
                ->where('is_current', true)->update(['is_current' => false, 'current_scope_key' => null]);
            $version->update(['status' => 'active', 'is_current' => true, 'current_scope_key' => $version->scopeKey(), 'activated_by' => auth()->id(), 'activated_at' => now()]);
        });
        $version = $version->fresh(['language', 'country', 'test', 'creator', 'activator']);
        $this->notifications->send('activated', $version);
        return response()->json(['success' => true, 'data' => $version]);
    }

    public function archive(ContractTemplate $template, ContractTemplateVersion $version)
    {
        abort_unless($version->contract_template_id === $template->id, 404);
        abort_if($version->is_current, 422, 'business.contract_template_version_current');
        abort_unless($version->status === 'draft', 422, 'business.contract_template_version_locked');
        $version->update(['status' => 'archived', 'archived_by' => auth()->id(), 'archived_at' => now()]);
        $this->notifications->send('archived', $version);
        return response()->json(['success' => true, 'data' => $version->fresh(['language', 'country', 'creator', 'archiver'])]);
    }

    private function sanitizeContent(string $content, ?string $slug): string
    {
        preg_match_all('/{{\s*([a-zA-Z0-9_.]+)\s*}}/', $content, $matches);
        $unknown = collect($matches[1] ?? [])->map(fn ($key) => strtolower($key))->unique()
            ->reject(fn ($key) => in_array($key, PlaceholderRegistry::keysForSlug($slug), true));
        abort_if($unknown->isNotEmpty(), 422, 'business.unresolved_placeholders');

        return $this->sanitizer->clean($content);
    }
}
