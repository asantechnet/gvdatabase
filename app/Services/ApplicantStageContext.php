<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\ApplicantContract;
use App\Models\ApplicantStage;
use App\Support\BuiltInService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * The one rule that decides whether a stage applies to a case.
 *
 * A stage's service is its workflow. Beyond that, an admission stage may be
 * scoped to a destination country or university, and a test-offer stage may
 * be scoped to one educational test (null means shared by every test). Academy
 * has no dependency at all.
 *
 * The context is resolved from the case's own service, never from the
 * applicant's mirrored service: one applicant can own several service lines,
 * and each line has its own detail row. Reads and writes both go through this
 * class, so a picker can never offer a stage the model guard would refuse.
 */
class ApplicantStageContext
{
    /** Whether the stage may be used by this case. */
    public function appliesTo(Applicant $applicant, ApplicantStage $stage, ?string $service = null): bool
    {
        $service ??= $applicant->service;

        if (! BuiltInService::isValid($service) || BuiltInService::isCaseFree($service)) {
            return false;
        }

        // A stage scoped to another service can never apply to this case.
        if ($stage->service !== null && $stage->service !== $service) {
            return false;
        }

        $detail = $this->detail($applicant, $service);

        return match ($service) {
            BuiltInService::ACADEMY => true,
            BuiltInService::TEST_OFFER => $stage->test_id === null
                || (int) $stage->test_id === (int) ($detail?->test_id ?? 0),
            BuiltInService::SCHOLARSHIP, BuiltInService::PRIVATE => $this->matchesAdmissionScope($stage, $detail),
            default => false,
        };
    }

    /** Active stages that apply to one service, ordered like the pipeline. */
    public function stagesFor(Applicant $applicant, string $service): Collection
    {
        return $this->queryFor($applicant, $service)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /** The query form of the same rule, for callers that need SQL filters. */
    public function queryFor(Applicant $applicant, string $service): Builder
    {
        $query = ApplicantStage::query()->where('is_active', true);

        if (! BuiltInService::isValid($service) || BuiltInService::isCaseFree($service)) {
            return $query->whereRaw('1 = 0');
        }

        $query->where('service', $service);
        $detail = $this->detail($applicant, $service);

        if ($service === BuiltInService::TEST_OFFER) {
            $testId = $detail?->test_id;

            return $query->where(function (Builder $scope) use ($testId): void {
                $scope->whereNull('test_id');
                if ($testId) {
                    $scope->orWhere('test_id', $testId);
                }
            });
        }

        if (in_array($service, [BuiltInService::SCHOLARSHIP, BuiltInService::PRIVATE], true)) {
            $countryId = $detail?->country_id;
            $universityId = $detail?->university_id;

            $query->where(function (Builder $scope) use ($countryId, $universityId): void {
                $scope->where(fn (Builder $unscoped) => $unscoped
                    ->whereNull('country_id')
                    ->whereNull('university_id'));
                if ($countryId) {
                    $scope->orWhere(fn (Builder $stage) => $stage
                        ->where('country_id', $countryId)
                        ->whereNull('university_id'));
                }
                if ($universityId) {
                    $scope->orWhere('university_id', $universityId);
                }
            });
        }

        return $query;
    }

    /** The service that governs a contract line. */
    public function serviceOfLine(ApplicantContract $line): ?string
    {
        return $line->serviceKey() ?? $line->applicant?->service;
    }

    /** The applicant's detail row for one service, independent of its mirror. */
    public function detail(Applicant $applicant, string $service): ?Model
    {
        $relation = match ($service) {
            BuiltInService::TEST_OFFER => 'testDetail',
            BuiltInService::ACADEMY => 'academyDetail',
            BuiltInService::PRIVATE => 'privateDetail',
            BuiltInService::SCHOLARSHIP => 'scholarshipDetail',
            default => null,
        };

        return $relation ? $applicant->{$relation} : null;
    }

    private function matchesAdmissionScope(ApplicantStage $stage, ?Model $detail): bool
    {
        $countryId = $detail?->country_id;
        $universityId = $detail?->university_id;

        if ($stage->country_id !== null && (int) $stage->country_id !== (int) $countryId) {
            return false;
        }

        if ($stage->university_id !== null && (int) $stage->university_id !== (int) $universityId) {
            return false;
        }

        return true;
    }
}
