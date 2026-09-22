<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\EducationLevel;
use App\Models\Language;
use App\Models\Package;
use App\Models\Semester;
use App\Models\University;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
        'package-types' => Package::class,
        'universities' => University::class,
        'semesters' => Semester::class,
    ];

    public function index(Request $request, string $catalog)
    {
        $this->authorizeCatalog($request, $catalog, 'view');
        $model = $this->model($catalog);
        $query = $model::query()->when($request->boolean('active'), fn ($query) => $query->where('is_active', true));
        if ($catalog === 'universities') {
            $query->with('country')->orderBy('name');
        } elseif ($catalog === 'package-types') {
            $query->with(['services.currency', 'currency'])->orderBy('name');
        } elseif (in_array($catalog, ['education-levels', 'semesters'], true)) {
            $query->orderBy('sort_order')->orderBy('name');
        } else {
            $query->orderBy('name');
        }

        return response()->json(['success' => true, 'data' => $query->get()]);
    }

    public function store(Request $request, string $catalog)
    {
        $this->authorizeCatalog($request, $catalog, 'create');
        $model = $this->model($catalog);
        $data = $this->validated($request, $catalog, creating: true);
        $record = DB::transaction(function () use ($model, $catalog, $data) {
            $serviceIds = $data['service_ids'] ?? null;
            unset($data['service_ids']);
            if ($serviceIds !== null) {
                $data['service_manifest'] = array_values($serviceIds);
            }
            $record = $this->restoreOrCreate($model, $catalog, $data);
            if ($record instanceof Package) {
                $record->services()->sync(collect($serviceIds)->values()->mapWithKeys(fn ($id, $index) => [$id => ['sort_order' => $index]])->all());
            }

            return $record;
        });

        return response()->json(['success' => true, 'data' => $this->loadCatalog($record, $catalog)], 201);
    }

    public function show(Request $request, string $catalog, int $id)
    {
        $this->authorizeCatalog($request, $catalog, 'view');
        $record = $this->model($catalog)::query()->findOrFail($id);

        return response()->json(['success' => true, 'data' => $this->loadCatalog($record, $catalog)]);
    }

    public function update(Request $request, string $catalog, int $id)
    {
        $this->authorizeCatalog($request, $catalog, 'edit');
        $record = $this->model($catalog)::query()->findOrFail($id);
        $data = $this->validated($request, $catalog, $record);
        DB::transaction(function () use ($record, $data): void {
            $serviceIds = $data['service_ids'] ?? null;
            unset($data['service_ids']);
            if ($serviceIds !== null) {
                $data['service_manifest'] = array_values($serviceIds);
            }
            $record->update($data);
            if ($record instanceof Package && $serviceIds !== null) {
                $record->services()->sync(collect($serviceIds)->values()->mapWithKeys(fn ($id, $index) => [$id => ['sort_order' => $index]])->all());
            }
        });

        return response()->json(['success' => true, 'data' => $this->loadCatalog($record->fresh(), $catalog)]);
    }

    public function destroy(Request $request, string $catalog, int $id)
    {
        $this->authorizeCatalog($request, $catalog, 'delete');
        $record = $this->model($catalog)::query()->findOrFail($id);
        if ($this->isInUse($record)) {
            return $this->inUse();
        }
        $record->delete();

        return response()->noContent();
    }

    /** @return class-string<Model> */
    private function model(string $catalog): string
    {
        abort_unless(isset(self::MODELS[$catalog]), 404);

        return self::MODELS[$catalog];
    }

    private function validated(Request $request, string $catalog, ?Model $record = null, bool $creating = false): array
    {
        $required = $record ? 'sometimes' : 'required';
        $unique = fn (string $table, string $column = 'name') => Rule::unique($table, $column)
            ->when($creating, fn ($rule) => $rule->whereNull('deleted_at'))
            ->ignore($record?->id);
        $rules = match ($catalog) {
            'countries' => ['name' => [$required, 'string', 'max:255', $unique('countries')], 'code' => [$required, 'string', 'max:3', $unique('countries', 'code')], 'is_active' => ['sometimes', 'boolean']],
            'education-levels' => ['name' => [$required, 'string', 'max:255', $unique('education_levels')], 'description' => ['nullable', 'string'], 'is_active' => ['sometimes', 'boolean'], 'sort_order' => ['sometimes', 'integer', 'min:0']],
            'languages' => ['name' => [$required, 'string', 'max:255', $unique('languages')], 'code' => [$required, 'string', 'max:10', $unique('languages', 'code')], 'is_active' => ['sometimes', 'boolean']],
            'package-types' => ['name' => [$required, 'string', 'max:255', $unique('package_types')], 'description' => ['nullable', 'string'], 'is_active' => ['sometimes', 'boolean'], 'currency_id' => ['nullable', 'integer', 'exists:currencies,id'], 'fee' => ['nullable', 'numeric', 'gte:0'], 'service_ids' => [$record ? 'sometimes' : 'required', 'array', 'min:1'], 'service_ids.*' => ['integer', 'distinct', Rule::exists('services', 'id')->whereNull('deleted_at')->where('status', 'active')]],
            'semesters' => ['name' => [$required, 'string', 'max:255', $unique('semesters')], 'code' => [$required, 'string', 'max:30', $unique('semesters', 'code')], 'sort_order' => ['sometimes', 'integer', 'min:0'], 'is_active' => ['sometimes', 'boolean']],
            'universities' => ['name' => [$required, 'string', 'max:255', Rule::unique('universities', 'name')->where(fn ($query) => $query->where('country_id', $request->input('country_id', $record?->country_id))->when($creating, fn ($query) => $query->whereNull('deleted_at')))->ignore($record?->id)], 'country_id' => [$required, 'integer', 'exists:countries,id'], 'city' => ['nullable', 'string', 'max:255'], 'website' => ['nullable', 'url', 'max:255'], 'description' => ['nullable', 'string'], 'is_active' => ['sometimes', 'boolean']],
        };
        $data = $request->validate($rules);
        foreach (['code'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = strtoupper($data[$field]);
            }
        }

        return $data;
    }

    /** @param class-string<Model> $model */
    private function restoreOrCreate(string $model, string $catalog, array $data): Model
    {
        $query = $model::onlyTrashed();

        match ($catalog) {
            'countries', 'languages' => $query->where(fn ($query) => $query
                ->where('name', $data['name'])
                ->orWhere('code', $data['code'])),
            'education-levels', 'package-types', 'semesters' => $query->where('name', $data['name']),
            'universities' => $query->where('name', $data['name'])
                ->where('country_id', $data['country_id'] ?? null),
        };

        $matches = $query->get();
        if ($matches->count() > 1) {
            throw ValidationException::withMessages([
                'name' => ['A deleted catalog entry conflicts with these unique values. Restore or rename it first.'],
            ]);
        }

        /** @var Model|null $record */
        $record = $matches->first();
        if ($record) {
            $record->restore();
            $record->update($data);

            return $record;
        }

        return $model::create($data);
    }

    private function inUse()
    {
        return response()->json(['code' => 'business.master_data_in_use'], 409);
    }

    private function isInUse(Model $record): bool
    {
        return match (true) {
            $record instanceof Country => $record->universities()->exists() || $record->applicants()->exists(),
            $record instanceof EducationLevel => $record->applicants()->exists(),
            $record instanceof Language => $record->applicants()->exists() || $record->contractTemplateVersions()->exists(),
            $record instanceof Package => $record->customerPackages()->exists(),
            $record instanceof University => $record->applicants()->exists(),
            $record instanceof Semester => $record->applicants()->exists(),
            default => false,
        };
    }

    private function authorizeCatalog(Request $request, string $catalog, string $action): void
    {
        $singular = ['countries' => 'country', 'education-levels' => 'education_level', 'languages' => 'language', 'package-types' => 'package_type', 'universities' => 'university', 'semesters' => 'semester'][$catalog];
        abort_unless($request->user()?->hasPermission("{$action}_{$singular}"), 403, 'auth.forbidden');
    }

    private function loadCatalog(Model $record, string $catalog): Model
    {
        return match ($catalog) {
            'universities' => $record->load('country'),
            'package-types' => $record->load(['services.currency', 'currency']),
            default => $record,
        };
    }
}
