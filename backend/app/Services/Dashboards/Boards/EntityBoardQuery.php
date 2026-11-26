<?php

declare(strict_types=1);

namespace App\Services\Dashboards\Boards;

use App\Contracts\Dashboards\DashboardBoardQuery;
use App\Data\Dashboards\BoardContent;
use App\Data\Dashboards\DashboardCard;
use App\Data\Dashboards\DashboardWidget;
use App\Data\Paths\NextStep;
use App\Enums\Assessments\AdvancedAssessmentStatus as Advanced;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Dashboards\CardFormat;
use App\Enums\Dashboards\DashboardBoard;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\EvidenceFile;
use App\Models\Award\AwardCycle;
use App\Models\Entities\Entity;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Models\Notifications\Notification;
use App\Services\Dashboards\EntityPaymentsCard;
use App\Services\Dashboards\EntityResults;
use App\Services\Paths\EntityPathsReader;
use App\Services\Paths\NextStepResolver;
use App\Support\Assessments\PathPresenter;
use App\Support\Assessments\StatusMask;
use App\Support\Certificates\CertificateSummaryPresenter;
use App\Support\Certificates\EntityLevelPresenter;
use App\Support\Dashboards\DashboardLinks as Links;
use App\Support\Paths\EntityPathsPresenter;
use App\Support\Paths\NextStepPresenter;
use Illuminate\Database\Eloquent\Builder;

final readonly class EntityBoardQuery implements DashboardBoardQuery
{
    private const int LATEST_REQUESTS = 5;

    public function __construct(
        private EntityResults $results,
        private EntityPaymentsCard $payments,
        private EntityPathsReader $paths,
        private NextStepResolver $nextStep,
    ) {}

    public function board(): DashboardBoard
    {
        return DashboardBoard::Entity;
    }

    public function build(User $user): BoardContent
    {
        $entityId = $user->entity_id;

        if ($entityId === null) {
            return new BoardContent([], []);
        }

        $latest = $this->results->latestOf($entityId);
        $summary = $this->results->summary($latest);
        $entity = Entity::withTrashed()->with('levelCertificate')->findOrFail($entityId);
        $paths = $this->paths->read($entity);
        $nextStep = $this->nextStep->resolve($paths, $user);

        return new BoardContent([
            new DashboardCard('latest_result_percent', $summary['percent'] ?? null, CardFormat::Percent, Links::ASSESSMENT_HISTORY),
            new DashboardCard('open_requests', $this->openRequests($entityId), link: Links::ASSESSMENT_HISTORY),
            new DashboardCard('unread_notifications', Notification::query()->ownedBy($user->id)->inInbox()->unread()->count(), link: Links::NOTIFICATIONS),
            new DashboardCard('accepted_evidence_count', $this->acceptedEvidence($entityId)),
        ], [
            new DashboardWidget('latest_result', $summary),
            new DashboardWidget('criteria_progress', $this->results->mainsOf($this->results->latestEligibleAccreditedOf($entityId))),
            new DashboardWidget('requests', $this->latestRequests($user, $entityId)),
            new DashboardWidget('payments_card', $this->payments->of($entityId)),
            new DashboardWidget('level', [
                'level' => EntityLevelPresenter::levelOf($entity)?->value,
                'certificate' => CertificateSummaryPresenter::present($entity->levelCertificate),
            ]),
            new DashboardWidget('paths', EntityPathsPresenter::present($paths, $user)),
            new DashboardWidget('next_step', $nextStep instanceof NextStep ? NextStepPresenter::present($nextStep) : null),
        ]);
    }

    private function openRequests(int $entityId): int
    {
        return Assessment::query()
            ->ownedBy($entityId)
            ->where(static fn (Builder $open): Builder => $open
                ->open()
                ->orWhere(static fn (Builder $masked): Builder => $masked
                    ->where('path', AssessmentPath::Advanced)
                    ->whereIn('status', Advanced::valuesOf(...Advanced::filteringVisibleStates()))
                    ->whereIn('award_cycle_id', AwardCycle::query()->select('id')->whereNull('announced_at'))))
            ->count();
    }

    private function acceptedEvidence(int $entityId): int
    {
        return EvidenceFile::query()
            ->present()
            ->whereIn('assessment_id', Assessment::query()->ownedBy($entityId)->select('id'))
            ->whereIn('stored_file_id', StoredFile::query()->clean()->select('id'))
            ->count();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function latestRequests(User $user, int $entityId): array
    {
        return array_values(Assessment::query()
            ->ownedBy($entityId)
            ->with('awardCycle:id,announced_at')
            ->latest('created_at')
            ->latest('id')
            ->limit(self::LATEST_REQUESTS)
            ->get(['id', 'request_number', 'entity_id', 'path', 'status', 'award_cycle_id', 'created_at', 'submitted_at'])
            ->map(static fn (Assessment $assessment): array => [
                'id' => $assessment->id,
                'number' => $assessment->request_number,
                ...PathPresenter::of($assessment->path),
                'status' => StatusMask::statusFor($assessment, $user),
                'is_open' => StatusMask::isOpenFor($assessment, $user),
                'created_at' => $assessment->created_at->toIso8601String(),
                'submitted_at' => $assessment->submitted_at?->toIso8601String(),
            ])
            ->all());
    }
}
