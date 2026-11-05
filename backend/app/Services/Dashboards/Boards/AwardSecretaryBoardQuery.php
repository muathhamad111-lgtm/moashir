<?php

declare(strict_types=1);

namespace App\Services\Dashboards\Boards;

use App\Contracts\Dashboards\DashboardBoardQuery;
use App\Data\Dashboards\BoardContent;
use App\Data\Dashboards\DashboardCard;
use App\Data\Dashboards\DashboardWidget;
use App\Enums\Assessments\AdvancedAssessmentStatus as Advanced;
use App\Enums\Award\CommitteeAssignmentStatus;
use App\Enums\Award\CycleMemberRole;
use App\Enums\Dashboards\CardFormat;
use App\Enums\Dashboards\DashboardBoard;
use App\Models\Award\CommitteeAssignment;
use App\Models\Identity\User;
use App\Services\Dashboards\CommitteeStandings;
use App\Services\Dashboards\MemberCycleRequests;
use App\Support\Dashboards\AggregateRow;
use App\Support\Dashboards\DashboardLinks as Links;
use App\Support\Dashboards\Ratio;

final readonly class AwardSecretaryBoardQuery implements DashboardBoardQuery
{
    private const int TOP_ENTITIES = 10;

    private const array WINNERS = [Advanced::RegionalWinner, Advanced::QualifiedForNational, Advanced::NationalWinner];

    public function __construct(private MemberCycleRequests $requests, private CommitteeStandings $standings) {}

    public function board(): DashboardBoard
    {
        return DashboardBoard::AwardSecretary;
    }

    public function build(User $user): BoardContent
    {
        $requests = $this->requests->of($user, CycleMemberRole::AwardSecretary);
        $counts = $this->counts($user);

        return new BoardContent([
            new DashboardCard('applicants', $counts['applicants'] ?? 0, link: Links::SECRETARY),
            new DashboardCard('qualified', $counts['qualified'] ?? 0, link: Links::SECRETARY),
            new DashboardCard('not_qualified', $counts['not_qualified'] ?? 0),
            new DashboardCard('in_committee', $counts['in_committee'] ?? 0, link: Links::SECRETARY),
            new DashboardCard('awaiting_approval', $counts['awaiting_approval'] ?? 0, link: Links::SECRETARY),
            new DashboardCard('results_approved', $counts['results_approved'] ?? 0, link: Links::RESULTS),
            new DashboardCard('winners', $counts['winners'] ?? 0, link: Links::RESULTS),
            new DashboardCard('committee_progress', $this->committeeProgress($user), CardFormat::Percent),
        ], [
            new DashboardWidget('cycles', $this->requests->cycles($user, CycleMemberRole::AwardSecretary)),
            new DashboardWidget('top_entities', $this->standings->top($requests, self::TOP_ENTITIES)),
            new DashboardWidget('score_histogram', $this->standings->histogram($requests)),
            new DashboardWidget('by_region', $this->standings->byRegion($requests)),
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function counts(User $user): array
    {
        $qualified = Advanced::valuesOf(...Advanced::qualifiedStates());
        $committee = Advanced::valuesOf(...Advanced::committeeStates());
        $results = Advanced::valuesOf(...Advanced::resultStates());
        $winners = Advanced::valuesOf(...self::WINNERS);

        return AggregateRow::counts($this->requests->of($user, CycleMemberRole::AwardSecretary), [
            'applicants' => ['true', []],
            'qualified' => ['status in ('.AggregateRow::placeholders($qualified).')', $qualified],
            'not_qualified' => ['status = ?', [Advanced::NotQualified->value]],
            'in_committee' => ['status in ('.AggregateRow::placeholders($committee).')', $committee],
            'awaiting_approval' => ['status = ?', [Advanced::AwaitingSecretaryApproval->value]],
            'results_approved' => ['status in ('.AggregateRow::placeholders($results).')', $results],
            'winners' => ['status in ('.AggregateRow::placeholders($winners).')', $winners],
        ]);
    }

    private function committeeProgress(User $user): ?string
    {
        $counts = AggregateRow::counts(
            CommitteeAssignment::query()->live()->whereIn('assessment_id', $this->requests->of($user, CycleMemberRole::AwardSecretary)->select('id')),
            ['total' => ['true', []], 'completed' => ['status = ?', [CommitteeAssignmentStatus::Completed->value]]],
        );

        return Ratio::percent($counts['completed'] ?? 0, $counts['total'] ?? 0);
    }
}
