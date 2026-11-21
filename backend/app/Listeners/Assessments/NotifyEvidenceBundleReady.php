<?php

declare(strict_types=1);

namespace App\Listeners\Assessments;

use App\Enums\Notifications\NotificationCode;
use App\Events\Assessments\EvidenceBundleReady;
use App\Services\Entities\EntityNotifier;
use App\Support\Assessments\AssessmentLinks;
use App\Support\Assessments\RequestNotices;
use App\Support\ValueObjects\LocalizedText;

final readonly class NotifyEvidenceBundleReady
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(EvidenceBundleReady $event): void
    {
        $bundle = $event->bundle;
        $assessment = $bundle->assessment->loadMissing('entity');

        $this->notifier->toStaffUser($bundle->requested_by_user_id, RequestNotices::for(
            NotificationCode::EvidenceBundleReady,
            RequestNotices::FOR_STAFF,
            $assessment,
            AssessmentLinks::evidenceBundle($assessment->id, $bundle->public_id),
            ['files' => LocalizedText::fromChoice('quantities.files', $bundle->file_count)],
        )->inAppOnly());
    }
}
