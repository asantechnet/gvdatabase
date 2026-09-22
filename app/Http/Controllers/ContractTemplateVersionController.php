<?php

namespace App\Http\Controllers;

use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use App\Services\Contracts\ContentSanitizer;
use App\Services\Contracts\PlaceholderRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContractTemplateVersionController extends Controller
{
    public function __construct(private readonly ContentSanitizer $sanitizer) {}

    public function index(ContractTemplate $template)
    {
        return response()->json(['success' => true, 'data' => $template->versions()->with(['language', 'country'])->orderByDesc('version_number')->get()]);
    }

    public function store(Request $request, ContractTemplate $template)
    {
        $data = $request->validate([
            'country_id' => ['nullable', 'exists:countries,id'],
            'language_id' => ['required', 'exists:languages,id'],
            'content' => ['required', 'string'],
            'status' => ['sometimes', 'in:draft,active,archived'],
        ]);
        $data['content'] = $this->sanitizeContent($data['content'], $template->contractType?->slug);

        return DB::transaction(function () use ($data, $request, $template) {
            $scope = $template->versions()->where('language_id', $data['language_id'])->where('country_id', $data['country_id'] ?? null);
            $number = (int) $scope->lockForUpdate()->max('version_number') + 1;
            $active = ($data['status'] ?? 'draft') === 'active';
            if ($active) $template->versions()->where('language_id', $data['language_id'])->where('country_id', $data['country_id'] ?? null)->update(['is_current' => false]);
            $version = $template->versions()->create([...$data, 'version_number' => $number, 'is_current' => $active, 'created_by' => $request->user()?->id]);
            return response()->json(['success' => true, 'data' => $version->load(['language', 'country'])], 201);
        });
    }

    public function activate(ContractTemplate $template, ContractTemplateVersion $version)
    {
        abort_unless($version->contract_template_id === $template->id, 404);
        DB::transaction(function () use ($template, $version) {
            $template->versions()->where('language_id', $version->language_id)->where('country_id', $version->country_id)->update(['is_current' => false]);
            $version->update(['status' => 'active', 'is_current' => true]);
        });
        return response()->json(['success' => true, 'data' => $version->fresh(['language', 'country'])]);
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
