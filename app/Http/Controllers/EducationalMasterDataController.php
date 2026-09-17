<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\EducationLevel;
use App\Models\Language;
use App\Models\PackageType;
use App\Models\University;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Thin CRUD adapter for small, data-driven educational catalogs. Keeping the
 * validation here ensures every consumer uses database records, never UI lists.
 */
class EducationalMasterDataController extends Controller
{
    private const MODELS = [
        'countries' => Country::class,
        'education-levels' => EducationLevel::class,
        'languages' => Language::class,
        'package-types' => PackageType::class,
        'universities' => University::class,
    ];

    public function index(Request $request, string $catalog)
    {
        $this->authorizeCatalog($request, $catalog, 'view');
        $model = $this->model($catalog);
        $query = $model::query()->when($request->boolean('active'), fn ($query) => $query->where('is_active', true));
        if ($catalog === 'universities') $query->with('country')->orderBy('name');
        elseif ($catalog === 'education-levels') $query->orderBy('sort_order')->orderBy('name');
        else $query->orderBy('name');
        return response()->json(['success' => true, 'data' => $query->get()]);
    }

    public function store(Request $request, string $catalog)
    {
        $this->authorizeCatalog($request, $catalog, 'create');
        $model = $this->model($catalog);
        $record = $model::create($this->validated($request, $catalog));
        return response()->json(['success' => true, 'data' => $catalog === 'universities' ? $record->load('country') : $record], 201);
    }

    public function show(Request $request, string $catalog, int $id)
    {
        $this->authorizeCatalog($request, $catalog, 'view');
        $record = $this->model($catalog)::query()->findOrFail($id);
        return response()->json(['success' => true, 'data' => $catalog === 'universities' ? $record->load('country') : $record]);
    }

    public function update(Request $request, string $catalog, int $id)
    {
        $this->authorizeCatalog($request, $catalog, 'edit');
        $record = $this->model($catalog)::query()->findOrFail($id);
        $record->update($this->validated($request, $catalog, $record));
        return response()->json(['success' => true, 'data' => $catalog === 'universities' ? $record->load('country') : $record]);
    }

    public function destroy(Request $request, string $catalog, int $id)
    {
        $this->authorizeCatalog($request, $catalog, 'delete');
        $record = $this->model($catalog)::query()->findOrFail($id);
        if ($this->isInUse($record)) return $this->inUse();
        $record->delete();
        return response()->noContent();
    }

    /** @return class-string<Model> */
    private function model(string $catalog): string { abort_unless(isset(self::MODELS[$catalog]), 404); return self::MODELS[$catalog]; }

    private function validated(Request $request, string $catalog, ?Model $record = null): array
    {
        $required = $record ? 'sometimes' : 'required';
        $rules = match ($catalog) {
            'countries' => ['name' => [$required, 'string', 'max:255', Rule::unique('countries')->ignore($record?->id)], 'code' => [$required, 'string', 'max:3', Rule::unique('countries')->ignore($record?->id)], 'is_active' => ['sometimes', 'boolean']],
            'education-levels' => ['name' => [$required, 'string', 'max:255', Rule::unique('education_levels')->ignore($record?->id)], 'description' => ['nullable', 'string'], 'is_active' => ['sometimes', 'boolean'], 'sort_order' => ['sometimes', 'integer', 'min:0']],
            'languages' => ['name' => [$required, 'string', 'max:255', Rule::unique('languages')->ignore($record?->id)], 'code' => [$required, 'string', 'max:10', Rule::unique('languages')->ignore($record?->id)], 'is_active' => ['sometimes', 'boolean']],
            'package-types' => ['name' => [$required, 'string', 'max:255', Rule::unique('package_types')->ignore($record?->id)], 'description' => ['nullable', 'string'], 'service_type' => ['nullable', 'string', 'max:100'], 'is_active' => ['sometimes', 'boolean']],
            'universities' => ['name' => [$required, 'string', 'max:255'], 'country_id' => ['nullable', 'integer', 'exists:countries,id'], 'city' => ['nullable', 'string', 'max:255'], 'website' => ['nullable', 'url', 'max:255'], 'description' => ['nullable', 'string'], 'is_active' => ['sometimes', 'boolean']],
        };
        $data = $request->validate($rules);
        foreach (['code'] as $field) if (isset($data[$field])) $data[$field] = strtoupper($data[$field]);
        return $data;
    }

    private function inUse() { return response()->json(['code' => 'business.master_data_in_use'], 409); }
    private function isInUse(Model $record): bool
    {
        return match (true) {
            $record instanceof Country => $record->universities()->exists() || $record->applicants()->exists(),
            $record instanceof EducationLevel => $record->applicants()->exists(),
            $record instanceof Language => $record->applicants()->exists() || $record->contractTemplateVersions()->exists(),
            $record instanceof PackageType => $record->scholarships()->exists() || $record->testOffers()->exists(),
            $record instanceof University => $record->applicants()->exists(),
            default => false,
        };
    }
    private function authorizeCatalog(Request $request, string $catalog, string $action): void { $singular = ['countries' => 'country', 'education-levels' => 'education_level', 'languages' => 'language', 'package-types' => 'package_type', 'universities' => 'university'][$catalog]; abort_unless($request->user()?->hasPermission("{$action}_{$singular}"), 403, 'auth.forbidden'); }
}
