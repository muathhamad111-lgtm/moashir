<?php

declare(strict_types=1);

namespace App\Actions\Periods;

use App\Data\Entities\EntityNotice;
use App\Enums\Notifications\NotificationCode;
use App\Enums\Periods\EntityPeriodState;
use App\Models\Assessments\AssessmentPeriod;
use App\Models\Entities\Entity;
use App\Services\Periods\PeriodAudience;
use App\Services\Periods\PeriodicSettingsReader;
use App\Services\Periods\PeriodNoticeSender;
use App\Services\Periods\PeriodStatusRecorder;
use App\Support\Assessments\AssessmentLinks;
use App\Support\Periods\LocalDay;
use App\Support\Periods\PeriodNotices;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Date;

final readonly class CloseEndedPeriods
{
    public const int MISSED_NOTICE_WINDOW_DAYS = 7;

    public function __construct(
        private PeriodicSettingsReader $settings,
        private PeriodStatusRecorder $recorder,
        private PeriodAudience $audience,
        private PeriodNoticeSender $notices,
    ) {}

    public function handle(): int
    {
        $today = LocalDay::today();
        $largestReminder = $this->settings->read()->largestReminderDays();
        $periods = AssessmentPeriod::query()->live()->whereNull('closed_at')->where('ends_on', '<', $today->toDateString())->orderBy('starts_on')->get();

        foreach ($periods as $period) {
            $this->recorder->recordEnded($period, $largestReminder);
            $this->notifyMissed($period, $today);
            $period->forceFill(['closed_at' => Date::now()])->save();
        }

        return $periods->count();
    }

    private function notifyMissed(AssessmentPeriod $period, CarbonImmutable $today): void
    {
        if ($period->ends_on->addDays(self::MISSED_NOTICE_WINDOW_DAYS)->toDateString() < $today->toDateString()) {
            return;
        }

        $missed = $this->audience->entities()->whereIn('id', static fn (QueryBuilder $statuses): QueryBuilder => $statuses
            ->select('entity_id')
            ->from('entity_period_statuses')
            ->where('assessment_period_id', $period->id)
            ->where('status', EntityPeriodState::Missed->value));

        $notice = PeriodNotices::for(NotificationCode::PeriodMissed, PeriodNotices::FOR_ENTITY, ['period' => $period->label()], AssessmentLinks::accreditedPath());
        $this->notices->send($period, NotificationCode::PeriodMissed, 0, $missed, static fn (Entity $entity): EntityNotice => $notice);
    }
}
