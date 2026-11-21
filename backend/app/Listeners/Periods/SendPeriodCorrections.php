<?php

declare(strict_types=1);

namespace App\Listeners\Periods;

use App\Data\Entities\EntityNotice;
use App\Enums\Notifications\NotificationCode;
use App\Events\Assessments\PeriodsSuperseded;
use App\Models\Assessments\AssessmentPeriod;
use App\Models\Entities\Entity;
use App\Services\Periods\PeriodAudience;
use App\Services\Periods\PeriodLocator;
use App\Services\Periods\PeriodNoticeSender;
use App\Support\Assessments\AssessmentLinks;
use App\Support\Periods\PeriodNotices;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;

final class SendPeriodCorrections implements ShouldQueue
{
    public int $tries = 3;

    public int $timeout = 300;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300];

    public function __construct(
        private readonly PeriodLocator $periods,
        private readonly PeriodAudience $audience,
        private readonly PeriodNoticeSender $notices,
    ) {}

    public function handle(PeriodsSuperseded $event): void
    {
        $next = $this->periods->firstUpcoming();

        if (! $next instanceof AssessmentPeriod) {
            return;
        }

        foreach (AssessmentPeriod::query()->whereKey($event->periodIds)->whereNotNull('notice_sent_at')->get() as $removed) {
            $this->correct($removed, $next);
        }
    }

    private function correct(AssessmentPeriod $removed, AssessmentPeriod $next): void
    {
        $notified = $this->audience->entities()->whereIn('id', static fn (QueryBuilder $notices): QueryBuilder => $notices
            ->select('entity_id')
            ->from('period_notices')
            ->where('assessment_period_id', $removed->id)
            ->where('code', NotificationCode::PeriodStartingSoon->value));

        $notice = PeriodNotices::for(NotificationCode::PeriodCorrected, PeriodNotices::FOR_ENTITY, [
            'period' => $removed->label(),
            'starts_on' => $next->starts_on->toDateString(),
            'ends_on' => $next->ends_on->toDateString(),
        ], AssessmentLinks::accreditedPath());

        $this->notices->send($removed, NotificationCode::PeriodCorrected, 0, $notified, static fn (Entity $entity): EntityNotice => $notice);
    }
}
