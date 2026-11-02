<?php

declare(strict_types=1);

namespace App\Listeners\Award;

use App\Data\Entities\EntityNotice;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Award\FilteringOutcome;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Award\CycleResultsAnnounced;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Award\AwardRanking;
use App\Models\Award\FilteringDecision;
use App\Services\Entities\EntityFreezeGuard;
use App\Services\Entities\EntityNotifier;
use App\Support\Award\AwardLinks;
use App\Support\Award\AwardNotices;
use Illuminate\Database\Eloquent\Collection;

final readonly class NotifyAwardResultsAnnounced
{
    private const int CHUNK = 200;

    private const array PARTICIPANT_STATES = [
        AdvancedAssessmentStatus::NotQualified, AdvancedAssessmentStatus::NonWinner,
        AdvancedAssessmentStatus::NationalWinner, AdvancedAssessmentStatus::NationalNonWinner,
    ];

    public function __construct(private EntityNotifier $notifier, private EntityFreezeGuard $freezeGuard) {}

    public function handle(CycleResultsAnnounced $event): void
    {
        $cycle = $event->cycle;

        $this->freezeGuard->excludeFrozen(Assessment::query()->inCycle($cycle->id)->whereIn('status', AdvancedAssessmentStatus::valuesOf(...self::PARTICIPANT_STATES)))
            ->with(['entity', 'ranking', 'latestFilteringDecision'])
            ->chunkById(self::CHUNK, function (Collection $participants) use ($cycle): void {
                foreach ($participants as $participant) {
                    foreach ($this->noticesFor($cycle, $participant) as $notice) {
                        $this->notifier->toOwner($participant->entity, $notice);
                    }
                }
            });

        $this->notifier->toRole(RoleKey::SystemAdmin, AwardNotices::for(NotificationCode::AwardResultsAnnounced, 'staff', $cycle, AwardLinks::results($cycle->id)), $event->actorId);
    }

    /**
     * @return list<EntityNotice>
     */
    private function noticesFor(AwardCycle $cycle, Assessment $participant): array
    {
        $link = AwardLinks::entityResult($participant->id);
        $extra = ['number' => $participant->request_number];
        $qualified = $participant->latestFilteringDecision instanceof FilteringDecision && $participant->latestFilteringDecision->outcome === FilteringOutcome::Qualified;
        $notices = [
            AwardNotices::for($qualified ? NotificationCode::AdvancedQualified : NotificationCode::AdvancedNotQualified, 'entity', $cycle, $link, $extra),
            AwardNotices::for(NotificationCode::AwardResultsAnnounced, 'entity', $cycle, $link, $extra),
        ];

        if ($participant->status === AdvancedAssessmentStatus::NotQualified) {
            return $notices;
        }

        $ranking = $participant->ranking;
        $regionalRank = $ranking instanceof AwardRanking ? $ranking->regional_rank : null;
        $notices[] = $this->rankedNotice(NotificationCode::RegionalResult, $cycle, $link, $extra, $regionalRank);

        if ($participant->status !== AdvancedAssessmentStatus::NonWinner) {
            $notices[] = $this->rankedNotice(NotificationCode::NationalResult, $cycle, $link, $extra, $ranking instanceof AwardRanking ? $ranking->national_rank : null);
        }

        return $notices;
    }

    /**
     * @param  array<string, string>  $extra
     */
    private function rankedNotice(NotificationCode $code, AwardCycle $cycle, string $link, array $extra, ?int $rank): EntityNotice
    {
        return $rank === null
            ? AwardNotices::for($code, 'entity_non_winner', $cycle, $link, $extra)
            : AwardNotices::for($code, 'entity_winner', $cycle, $link, [...$extra, 'rank' => (string) $rank]);
    }
}
