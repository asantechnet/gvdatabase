<?php

namespace App\Http\Controllers;

use App\Models\AcademyClass;
use App\Models\AcquisitionRoute;
use App\Models\Country;
use App\Models\Duration;
use App\Models\EnglishLevel;
use App\Models\EducationalScholarship;
use App\Models\EducationLevel;
use App\Models\Language;
use App\Models\Program;
use App\Models\Province;
use App\Models\Semester;
use App\Models\Skill;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Test;
use App\Models\TimeSlot;
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
        'universities' => University::class,
        'semesters' => Semester::class,
        'subjects' => Subject::class,
        'tests' => Test::class,
        'skills' => Skill::class,
        'programs' => Program::class,
        'times' => TimeSlot::class,
        'durations' => Duration::class,
        'teachers' => Teacher::class,
        'scholarships' => EducationalScholarship::class,
        'classes' => AcademyClass::class,
        'provinces' => Province::class,
        'english-levels' => EnglishLevel::class,
        'acquisition-routes' => AcquisitionRoute::class,
    ];

    /** Sorted catalogues whose rows carry a display order. */
    private const SORTED_CATALOGS = [
        'education-levels', 'semesters', 'subjects', 'tests', 'skills',
        'programs', 'times', 'durations', 'teachers', 'scholarships', 'classes',
        'provinces', 'english-levels', 'acquisition-routes',
    ];

    public function index(Request $request, string $catalog)
    {
        $this->authorizeCatalog($request, $catalog, 'view');
        $model = $this->model($catalog);
        $query = $model::query()->when($request->boolean('active'), fn ($query) => $query->where('is_active', true));
        if ($catalog === 'universities') {
            $query->with('country')->orderBy('name');
        } elseif (in_array($catalog, self::SORTED_CATALOGS, true)) {
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
            return $this->restoreOrCreate($model, $catalog, $data);
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
            $record->update($data);
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
            'countries' => ['name' => [$required, 'string', 'max:255', $unique('countries')], 'code' => [$required, 'string', 'max:3', $unique('countries', 'code')], 'phone' => ['nullable', 'string', 'max:50'], 'email' => ['nullable', 'email', 'max:255'], 'portal_url' => ['nullable', 'string'], 'is_active' => ['sometimes', 'boolean']],
            'education-levels' => ['name' => [$required, 'string', 'max:255', $unique('education_levels')], 'description' => ['nullable', 'string'], 'is_active' => ['sometimes', 'boolean'], 'sort_order' => ['sometimes', 'integer', 'min:0']],
            'languages' => ['name' => [$required, 'string', 'max:255', $unique('languages')], 'code' => [$required, 'string', 'max:10', $unique('languages', 'code')], 'is_active' => ['sometimes', 'boolean']],
            'semesters' => ['name' => [$required, 'string', 'max:255', $unique('semesters')], 'code' => [$required, 'string', 'max:30', $unique('semesters', 'code')], 'sort_order' => ['sometimes', 'integer', 'min:0'], 'is_active' => ['sometimes', 'boolean']],
            'universities' => ['name' => [$required, 'string', 'max:255', Rule::unique('universities', 'name')->where(fn ($query) => $query->where('country_id', $request->input('country_id', $record?->country_id))->when($creating, fn ($query) => $query->whereNull('deleted_at')))->ignore($record?->id)], 'country_id' => [$required, 'integer', 'exists:countries,id'], 'city' => ['nullable', 'string', 'max:255'], 'website' => ['nullable', 'url', 'max:255'], 'phone' => ['nullable', 'string', 'max:50'], 'email' => ['nullable', 'email', 'max:255'], 'portal_url' => ['nullable', 'string'], 'description' => ['nullable', 'string'], 'is_active' => ['sometimes', 'boolean']],
            'teachers' => ['name' => [$required, 'string', 'max:255', $unique('teachers')], 'contact' => ['nullable', 'string', 'max:255'], 'email' => ['nullable', 'email', 'max:255'], 'description' => ['nullable', 'string'], 'sort_order' => ['sometimes', 'integer', 'min:0'], 'is_active' => ['sometimes', 'boolean']],
            'subjects', 'tests', 'skills', 'programs', 'times', 'durations', 'scholarships', 'classes', 'provinces', 'english-levels', 'acquisition-routes' => ['name' => [$required, 'string', 'max:255', $unique(match ($catalog) {
                'classes' => 'academy_classes', 'scholarships' => 'educational_scholarships',
                'english-levels' => 'english_levels', 'acquisition-routes' => 'acquisition_routes', default => $catalog
            })], 'description' => ['nullable', 'string'], 'sort_order' => ['sometimes', 'integer', 'min:0'], 'is_active' => ['sometimes', 'boolean']],
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
            'education-levels', 'semesters', 'subjects', 'tests', 'skills', 'programs', 'times', 'durations', 'teachers',
            'provinces', 'english-levels', 'acquisition-routes' => $query->where('name', $data['name']),
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
        if (in_array($record::class, [Country::class, EducationLevel::class, University::class, Semester::class], true)) {
            $field = [Country::class => 'country_id', EducationLevel::class => 'education_level_id', University::class => 'university_id', Semester::class => 'semester_id'][$record::class];
            if (DB::table('private_applicant_details')->where($field, $record->id)->exists()) {
                return true;
            }
        }

        return match (true) {
            $record instanceof Country => $record->universities()->exists() || $record->applicants()->exists(),
            $record instanceof EducationLevel => $record->applicants()->exists(),
            $record instanceof Language => $record->contractTemplateVersions()->exists(),
            $record instanceof University => $record->applicants()->exists(),
            $record instanceof Semester => $record->applicants()->exists(),
            // Catalogues referenced by the per-service applicant details.
            $record instanceof Subject => DB::table('scholarship_applicant_subject')->where('subject_id', $record->id)->exists(),
            $record instanceof Skill => DB::table('test_applicant_skill')->where('skill_id', $record->id)->exists(),
            $record instanceof Teacher => DB::table('academy_applicant_teacher')->where('teacher_id', $record->id)->exists(),
            $record instanceof Test => DB::table('test_applicant_details')->where('test_id', $record->id)->exists()
                // A stage of any state (including a soft-deleted one) still
                // names this test; deleting it would orphan the stage scope.
                || DB::table('applicant_stages')->where('test_id', $record->id)->exists(),
            $record instanceof Program => DB::table('academy_applicant_details')->where('program_id', $record->id)->exists(),
            $record instanceof TimeSlot => DB::table('academy_applicant_details')->where('time_id', $record->id)->exists(),
            $record instanceof Duration => DB::table('academy_applicant_details')->where('duration_id', $record->id)->exists(),
            $record instanceof EducationalScholarship => DB::table('scholarship_applicant_details')->where('scholarship_id', $record->id)->exists(),
            $record instanceof AcademyClass => DB::table('academy_applicant_details')->where('class_id', $record->id)->exists(),
            $record instanceof Province => DB::table('visitors')->where('province_id', $record->id)->whereNull('deleted_at')->exists(),
            $record instanceof EnglishLevel => DB::table('visitors')->where('english_level_id', $record->id)->whereNull('deleted_at')->exists(),
            $record instanceof AcquisitionRoute => DB::table('visitors')->where('acquisition_route_id', $record->id)->whereNull('deleted_at')->exists(),
            default => false,
        };
    }

    private function authorizeCatalog(Request $request, string $catalog, string $action): void
    {
        $singular = [
            'countries' => 'country', 'education-levels' => 'education_level', 'languages' => 'language',
            'universities' => 'university', 'semesters' => 'semester', 'subjects' => 'subject',
            'tests' => 'test', 'skills' => 'skill', 'programs' => 'program', 'times' => 'time',
            'durations' => 'duration', 'teachers' => 'teacher', 'scholarships' => 'subject', 'classes' => 'program',
            'provinces' => 'province', 'english-levels' => 'english_level', 'acquisition-routes' => 'acquisition_route',
        ][$catalog];
        abort_unless($request->user()?->hasPermission("{$action}_{$singular}"), 403, 'auth.forbidden');
    }

    private function loadCatalog(Model $record, string $catalog): Model
    {
        return match ($catalog) {
            'universities' => $record->load('country'),
            default => $record,
        };
    }
}
