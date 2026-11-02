<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Award\AwardResultsBoard;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Award\RankStage;
use App\Models\Award\AwardCycle;
use App\Models\Award\AwardRegionApproval;
use App\Services\Award\AwardResultsQuery;
use App\Support\ScalarValue;
use Illuminate\Database\Eloquent\Builder;

final readonly class ListAwardResults
{
    public function __construct(private AwardResultsQuery $results) {}

    public function handle(AwardCycle $cycle, RankStage $stage, ?int $regionId): AwardResultsBoard
    {
        $rankings = $this->results->rankings($cycle->id, $stage, $regionId)
            ->when($stage === RankStage::Regional, static fn (Builder $query): Builder => $query->orderBy('region_id'))
            ->orderByRaw($stage === RankStage::Regional ? 'regional_rank asc nulls last' : 'national_rank asc nulls last')
            ->orderByDesc('average_percent')
            ->orderBy('id')
            ->get();
        $approvals = [];

        foreach (AwardRegionApproval::query()->where('award_cycle_id', $cycle->id)->current()->get() as $approval) {
            $approvals[$approval->region_id] = $approval;
        }

        $settled = $stage === RankStage::Regional ? AdvancedAssessmentStatus::resultStates() : AdvancedAssessmentStatus::nationalStates();
        $pending = [];

        foreach ($this->results->unsettled($cycle->id, $settled)->toBase()->selectRaw('region_id, count(*) as aggregate')->groupBy('region_id')->pluck('aggregate', 'region_id') as $regionId => $count) {
            $pending[ScalarValue::int($regionId)] = ScalarValue::int($count);
        }

        return new AwardResultsBoard($cycle, array_values($rankings->all()), $approvals, $pending);
    }
}
