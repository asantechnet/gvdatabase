<?php

namespace App\Http\Controllers;

use App\Http\Resources\WhatsappTemplateResource;
use App\Models\WhatsappTemplate;
use App\Services\Whatsapp\WhatsappPlaceholderRegistry;
use App\Support\WhatsappAudience;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Reusable WhatsApp message templates.
 *
 * Templates are grouped by audience and access follows that audience's record
 * type: reading needs the record's view permission, changing needs its edit
 * permission. Sending is a `wa.me` deep link built by the client from a
 * delivered template, so this controller never talks to WhatsApp.
 */
class WhatsappTemplateController extends Controller
{
    /** WhatsApp's own text-body limit; the editor warns at the same number. */
    private const MESSAGE_LIMIT = 4096;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->validateQuery($request);

        $query = WhatsappTemplate::query()
            // A user only ever sees the audiences they can already open.
            ->whereIn('audience', WhatsappAudience::viewableBy($request->user()))
            ->ordered();

        if ($request->filled('audience')) {
            $query->where('audience', $request->query('audience'));
        }

        // The composer asks for active templates (what the quick-contact menus
        // offer); the management page asks for all of them.
        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        return WhatsappTemplateResource::collection($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $this->authorizeManage($request, $data['audience']);

        // State the column defaults explicitly: the created model instance is
        // what the response serializes, and a driver that does not return
        // inserted defaults would otherwise report `active` as false.
        $template = WhatsappTemplate::create([
            'active' => true,
            'sort_order' => 0,
            ...$this->payload($data),
        ]);

        return response()->json([
            'success' => true,
            'data' => new WhatsappTemplateResource($template),
        ], 201);
    }

    public function update(Request $request, WhatsappTemplate $whatsappTemplate): JsonResponse
    {
        // Changing a template needs the permission of the audience it belongs to,
        // and moving it to another audience needs that audience's permission too.
        $this->authorizeManage($request, $whatsappTemplate->audience);

        $data = $this->validated($request, $whatsappTemplate);

        if (array_key_exists('audience', $data)) {
            $this->authorizeManage($request, $data['audience']);
        }

        $whatsappTemplate->update($this->payload($data));

        return response()->json([
            'success' => true,
            'data' => new WhatsappTemplateResource($whatsappTemplate->refresh()),
        ]);
    }

    public function destroy(Request $request, WhatsappTemplate $whatsappTemplate): JsonResponse
    {
        $this->authorizeManage($request, $whatsappTemplate->audience);

        $whatsappTemplate->delete();

        return response()->json(['success' => true]);
    }

    /**
     * The placeholder catalogue for the audiences the caller may use.
     *
     * The frontend renders its palette, its preview samples and its prefill
     * from this payload, so the token list is never duplicated in TypeScript.
     */
    public function placeholders(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => WhatsappPlaceholderRegistry::groupsFor(WhatsappAudience::viewableBy($request->user())),
        ]);
    }

    private function validateQuery(Request $request): void
    {
        $request->validate([
            'audience' => ['nullable', 'string', 'in:'.implode(',', WhatsappAudience::ALL)],
            'active' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?WhatsappTemplate $template = null): array
    {
        // `sometimes` + `required` means "only when present, and never blank".
        $presence = $template ? ['sometimes', 'required'] : ['required'];

        $data = Validator::make($request->all(), [
            'audience' => [$template ? 'sometimes' : 'required', 'string', 'in:'.implode(',', WhatsappAudience::ALL)],
            'title' => [...$presence, 'string', 'max:150'],
            'labels' => ['nullable', 'array', 'max:20'],
            // A blank entry arrives as null (empty strings are converted before
            // validation); it is dropped by the normalisation below, not rejected.
            'labels.*' => ['nullable', 'string', 'max:50'],
            'message' => [...$presence, 'string', 'max:'.self::MESSAGE_LIMIT],
            'active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ])->validate();

        if (array_key_exists('message', $data)) {
            $this->assertMessageIsUsable($data['audience'] ?? $template?->audience ?? '', $data['message']);
        }

        if (array_key_exists('labels', $data)) {
            $data['labels'] = collect($data['labels'] ?? [])
                ->filter(fn ($label) => is_string($label) && trim($label) !== '')
                ->map(fn (string $label) => trim($label))
                ->unique()
                ->values()
                ->all();
        }

        return $data;
    }

    /** Reject a blank body, and a placeholder this audience cannot fill. */
    private function assertMessageIsUsable(string $audience, string $message): void
    {
        if (trim($message) === '') {
            throw ValidationException::withMessages(['message' => ['validation.required']]);
        }

        if (WhatsappPlaceholderRegistry::unknownTokens($audience, $message) !== []) {
            throw ValidationException::withMessages(['message' => ['validation.whatsapp_unknown_placeholder']]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data): array
    {
        // Store the body without the author's stray leading/trailing whitespace.
        if (array_key_exists('message', $data)) {
            $data['message'] = trim($data['message']);
        }

        return $data;
    }

    private function authorizeManage(Request $request, ?string $audience): void
    {
        abort_unless(WhatsappAudience::canManage($request->user(), $audience), 403, 'auth.forbidden');
    }
}
