<?php

declare(strict_types=1);

namespace App\Listeners\Assessments;

use App\Enums\Notifications\NotificationCode;
use App\Events\Assessments\CompletionRequested;
use App\Services\Entities\EntityNotifier;
use App\Support\Assessments\AssessmentLinks;
use App\Support\Assessments\RequestNotices;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Contracts\Config\Repository;

final readonly class NotifyCompletionRequested
{
    public function __construct(private EntityNotifier $notifier, private Repository $config) {}

    public function handle(CompletionRequested $event): void
    {
        $assessment = $event->assessment;
        $deadline = $event->completionRequest->deadline_at->copy()->setTimezone($this->config->string('app.display_timezone'))->format('Y-m-d H:i');

        $this->notifier->toOwner($assessment->entity, RequestNotices::for(
            NotificationCode::AccreditedCompletionRequested,
            RequestNotices::FOR_ENTITY,
            $assessment,
            AssessmentLinks::accreditedForm(),
            [
                'deadline' => $deadline,
                'outcome' => LocalizedText::fromTranslation('notifications.assessments.accredited.ACC-07.outcomes.'.$event->completionRequest->expiry_outcome->value),
            ],
        ));
    }
}
