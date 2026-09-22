<?php

namespace App\Http\Controllers;

use App\Models\EducationalBonusSource;
use App\Services\EducationalBonusSourceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EducationalBonusSourceController extends Controller
{
    public function __construct(private readonly EducationalBonusSourceService $bonusSources) {}

    public function index()
    {
        return response()->json(['success' => true, 'data' => EducationalBonusSource::query()
            ->with(['employee:id,name', 'customer:id,name', 'applicant.customer:id,name', 'source'])
            ->latest()
            ->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'source_type' => ['required', 'in:contract,service'],
            'source_id' => ['required', 'integer', 'min:1'],
            'source_amount' => ['nullable', 'numeric', 'gt:0'],
            'bonus_amount' => ['nullable', 'numeric', 'gte:0'],
            'rule_source' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:pending,approved,paid,void'],
        ]);
        $sourceClass = $this->bonusSources->sourceTypes()[$data['source_type']];
        /** @var Model $source */
        $source = $sourceClass::query()->findOrFail($data['source_id']);

        $bonus = DB::transaction(fn () => $this->bonusSources->createFor($source, $data));

        return response()->json(['success' => true, 'data' => $this->load($bonus)], 201);
    }

    public function update(Request $request, EducationalBonusSource $educationalBonusSource)
    {
        $data = $request->validate([
            'bonus_amount' => ['sometimes', 'numeric', 'gte:0'],
            'rule_source' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'in:pending,approved,paid,void'],
        ]);

        if (($data['status'] ?? null) === 'paid' && $educationalBonusSource->status !== 'approved') {
            abort(422, 'business.bonus_source_requires_approval');
        }
        if ($educationalBonusSource->status === 'paid' && $data !== []) {
            abort(422, 'business.paid_bonus_source_immutable');
        }

        $educationalBonusSource->update($data);

        return response()->json(['success' => true, 'data' => $this->load($educationalBonusSource->fresh())]);
    }

    private function load(EducationalBonusSource $bonus): EducationalBonusSource
    {
        return $bonus->load(['employee:id,name', 'customer:id,name', 'applicant.customer:id,name', 'source']);
    }
}
