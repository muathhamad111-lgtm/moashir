<?php

declare(strict_types=1);

namespace App\Actions\Periods;

use App\Data\Entities\EntityNotice;
use App\Data\Periods\PeriodicSettings;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Notifications\NotificationCode;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentPeriod;
use App\Models\Entities\Entity;
use App\Services\Periods\PeriodAudience;
use App\Services\Periods\PeriodicSettingsReader;
use App\Services\Periods\PeriodLocator;
use App\Services\Periods\PeriodNoticeSender;
use App\Support\Assessments\AssessmentLinks;
use App\Support\Periods\LocalDay;
use App\Support\Periods\PeriodNotices;
use App\Support\ValueObjects\LocalizedText;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

final readonly class SendPeriodNotices
{
    public function __construct(
        private PeriodicSettingsReader $settings,
        private PeriodLocator $periods,
        private PeriodAudience $audience,
        private PeriodNoticeSender $notices,
    ) {}

    public function handle(): int
    {
        $settings = $this->settings->read();
        $today = LocalDay::today();

        return $this->announceUpcoming($settings, $today) + $this->remindBeforeEnd($settings, $today);
    }

    private function announceUpcoming(PeriodicSettings $settings, CarbonImmutable $today): int
    {
        $upcoming = $this->periods->firstUpcoming();

        if (! $upcoming instanceof AssessmentPeriod || $upcoming->notice_sent_at !== null || $upcoming->noticeOn($settings->noticeDays)->toDateString() > $today->toDateString()) {
            return 0;
        }

        $notice = PeriodNotices::for(NotificationCode::PeriodStartingSoon, PeriodNotices::FOR_ENTITY, $this->datesOf($upcoming), AssessmentLinks::accreditedPath());
        $sent = $this->notices->send($upcoming, NotificationCode::PeriodStartingSoon, 0, $this->audience->entities(), static fn (Entity $entity): EntityNotice => $notice);
        $upcoming->forceFill(['notice_sent_at' => Date::now()])->save();

        return $sent;
    }

    private function remindBeforeEnd(PeriodicSettings $settings, CarbonImmutable $today): int
    {
        $current = $this->periods->current();

        if (! $current instanceof AssessmentPeriod) {
            return 0;
        }

        $daysLeft = (int) $today->diffInDays(LocalDay::parse($current->ends_on->toDateString()));
        $due = array_values(array_filter($settings->reminderDays, static fn (int $days): bool => $daysLeft <= $days && ! $current->hasSentReminder($days)));

        if ($due === []) {
            return 0;
        }

        $closest = min($due);
        $sent = $this->notices->send($current, NotificationCode::PeriodEndingSoon, $closest, $this->audience->withoutSubmissionIn($current->id), fn (Entity $entity): EntityNotice => $this->reminderFor($entity, $current, $daysLeft));
        $current->forceFill(['reminders_sent' => array_values(array_unique([...$current->reminders_sent, ...$due]))])->save();

        return $sent;
    }

    private function reminderFor(Entity $entity, AssessmentPeriod $period, int $daysLeft): EntityNotice
    {
        $draft = Assessment::query()->ownedBy($entity->id)->ofPath(AssessmentPath::Accredited)->where('status', AccreditedAssessmentStatus::Draft)->latest('id')->first();
        $replacements = [...$this->datesOf($period), 'days' => LocalizedText::fromChoice('quantities.days', max(1, $daysLeft))];

        if ($draft instanceof Assessment) {
            return PeriodNotices::for(NotificationCode::PeriodEndingSoon, 'draft.'.PeriodNotices::FOR_ENTITY, [...$replacements, 'number' => $draft->request_number], AssessmentLinks::accreditedRequestTab($draft->id));
        }

        return PeriodNotices::for(NotificationCode::PeriodEndingSoon, 'apply.'.PeriodNotices::FOR_ENTITY, $replacements, AssessmentLinks::accreditedPath());
    }

    /**
     * @return array{period: LocalizedText, starts_on: string, ends_on: string}
     */
    private function datesOf(AssessmentPeriod $period): array
    {
        return ['period' => $period->label(), 'starts_on' => $period->starts_on->toDateString(), 'ends_on' => $period->ends_on->toDateString()];
    }
}
