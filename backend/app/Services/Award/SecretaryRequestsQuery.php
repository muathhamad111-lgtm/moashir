<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Data\Award\SecretaryRequestFilters;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Award\CommitteeAverage;
use App\Services\Entities\EntityFreezeGuard;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Database\Eloquent\Builder;

final readonly class SecretaryRequestsQuery
{
    public const array SORTABLE = ['submitted_at', '-submitted_at', 'average', '-average'];

    public function __construct(private EntityFreezeGuard $freezeGuard) {}

    /**
     * @return Builder<Assessment>
     */
    public function filtered(AwardCycle $cycle, SecretaryRequestFilters $filters): Builder
    {
        $states = [...AdvancedAssessmentStatus::committeeStates(), ...AdvancedAssessmentStatus::resultStates()];
        $query = $this->freezeGuard->excludeFrozen(Assessment::query()->inCycle($cycle->id)->whereIn('status', AdvancedAssessmentStatus::valuesOf(...$states)));

        if ($filters->status instanceof AdvancedAssessmentStatus) {
            $query->where('status', $filters->status);
        }

        if ($filters->regionId !== null) {
            $query->where('region_id', $filters->regionId);
        }

        if ($filters->search instanceof SearchTerm) {
            $query->matchingSearch($filters->search);
        }

        return $query;
    }

    /**
     * @param  Builder<Assessment>  $query
     * @return Builder<Assessment>
     */
    public function sorted(Builder $query, string $sort): Builder
    {
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        if (ltrim($sort, '-') === 'average') {
            return $query->orderBy(CommitteeAverage::query()->select('percent')->whereColumn('committee_averages.id', 'assessments.committee_average_id'), $direction)->orderBy('id');
        }

        return $query->orderBy('submitted_at', $direction)->orderBy('id');
    }
}
