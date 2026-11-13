<?php

declare(strict_types=1);

namespace App\Services\Dashboards\Boards;

use App\Contracts\Dashboards\DashboardBoardQuery;
use App\Data\Dashboards\BoardContent;
use App\Data\Dashboards\DashboardCard;
use App\Data\Dashboards\DashboardWidget;
use App\Enums\Dashboards\DashboardBoard;
use App\Models\Assessments\Assessment;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Criteria\GuidingPoint;
use App\Models\Criteria\MainCriterion;
use App\Models\Criteria\SubCriterion;
use App\Models\Identity\User;
use App\Support\Dashboards\DashboardLinks as Links;

final readonly class CriteriaOfficerBoardQuery implements DashboardBoardQuery
{
    public function board(): DashboardBoard
    {
        return DashboardBoard::CriteriaOfficer;
    }

    public function build(User $user): BoardContent
    {
        $published = CriteriaVersion::query()->published()->first();
        $draft = CriteriaVersion::query()->draft()->first();
        $mains = MainCriterion::query()->select('id')->where('criteria_version_id', $published?->id)->where('is_active', true);
        $subs = SubCriterion::query()->select('id')->whereIn('main_criterion_id', $mains)->where('is_active', true);

        return new BoardContent([
            new DashboardCard('main_criteria', (clone $mains)->count(), link: Links::CRITERIA),
            new DashboardCard('sub_criteria', (clone $subs)->count(), link: Links::CRITERIA),
            new DashboardCard('guiding_points', GuidingPoint::query()->whereIn('sub_criterion_id', $subs)->where('is_active', true)->count(), link: Links::CRITERIA),
            new DashboardCard('open_assessments_on_published', $published instanceof CriteriaVersion ? Assessment::query()->open()->where('criteria_version_id', $published->id)->count() : 0),
            new DashboardCard('draft_problems', count($draft->validation_errors ?? []), link: Links::CRITERIA),
        ], [
            new DashboardWidget('published_version', $published instanceof CriteriaVersion ? [
                'id' => $published->id,
                'version_number' => $published->version_number,
                'label' => $published->label?->toArray(),
                'scale_kind' => $published->scale_kind?->value,
                'published_at' => $published->published_at?->toIso8601String(),
            ] : null),
            new DashboardWidget('draft_version', $draft instanceof CriteriaVersion ? [
                'id' => $draft->id,
                'version_number' => $draft->version_number,
                'label' => $draft->label?->toArray(),
                'problems' => count($draft->validation_errors ?? []),
                'updated_at' => $draft->updated_at->toIso8601String(),
            ] : null),
        ]);
    }
}
