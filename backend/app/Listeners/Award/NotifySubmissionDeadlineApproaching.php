<?php

declare(strict_types=1);

namespace App\Listeners\Award;

use App\Enums\Notifications\NotificationCode;
use App\Events\Award\SubmissionDeadlineApproaching;
use App\Models\Assessments\Assessment;
use App\Services\Entities\EntityNotifier;
use App\Support\Award\AwardLinks;
use App\Support\Award\AwardNotices;

final readonly class NotifySubmissionDeadlineApproaching
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(SubmissionDeadlineApproaching $event): void
    {
        $drafts = Assessment::query()->with('entity')->whereKey($event->draftIds)->get();

        foreach ($drafts as $draft) {
            $this->notifier->toOwner($draft->entity, AwardNotices::for(
                NotificationCode::AdvancedDeadlineApproaching,
                AwardNotices::FOR_ENTITY,
                $event->cycle,
                AwardLinks::entityIntro(),
                ['number' => $draft->request_number],
            ));
        }
    }
}
