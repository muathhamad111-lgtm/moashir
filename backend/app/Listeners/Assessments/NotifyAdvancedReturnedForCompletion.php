<?php

declare(strict_types=1);

namespace App\Listeners\Assessments;

use App\Enums\Notifications\NotificationCode;
use App\Events\Assessments\AdvancedReturnedForCompletion;
use App\Models\Assessments\AssessmentCompletionRequest;
use App\Services\Entities\EntityNotifier;
use App\Support\Assessments\AssessmentLinks;
use App\Support\Assessments\RequestNotices;
use App\Support\Award\AwardNotices;
use App\Support\ValueObjects\LocalizedText;

final readonly class NotifyAdvancedReturnedForCompletion
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(AdvancedReturnedForCompletion $event): void
    {
        $assessment = $event->assessment;
        $request = $assessment->loadMissing('openCompletionRequest')->openCompletionRequest;

        if (! $request instanceof AssessmentCompletionRequest) {
            return;
        }

        $this->notifier->toOwner($assessment->entity, RequestNotices::for(
            NotificationCode::AdvancedReturnedForCompletion,
            RequestNotices::FOR_ENTITY,
            $assessment,
            AssessmentLinks::advancedForm(),
            [
                'deadline' => AwardNotices::moment($request->deadline_at),
                'outcome' => LocalizedText::fromTranslation('notifications.assessments.advanced.ADV-25.outcomes.'.$request->expiry_outcome->value),
            ],
        ));
    }
}
