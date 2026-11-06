<?php

declare(strict_types=1);

namespace App\Listeners\Certificates;

use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Certificates\CertificateSuspended;
use App\Services\Entities\EntityNotifier;
use App\Support\Assessments\AssessmentLinks;
use App\Support\Assessments\RequestNotices;
use App\Support\Certificates\CertificateLinks;

final readonly class NotifyCertificateSuspended
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(CertificateSuspended $event): void
    {
        $certificate = $event->certificate->loadMissing('assessment.entity');
        $assessment = $certificate->assessment;
        $extra = ['certificate' => $certificate->number];

        $this->notifier->toOwner($assessment->entity, RequestNotices::for(
            NotificationCode::CertificateSuspended,
            RequestNotices::FOR_ENTITY,
            $assessment,
            CertificateLinks::ENTITY_CERTIFICATES,
            $extra,
        ));
        $this->notifier->toRole(RoleKey::SystemAdmin, RequestNotices::for(
            NotificationCode::CertificateSuspended,
            RequestNotices::FOR_STAFF,
            $assessment,
            AssessmentLinks::request($assessment->id),
            $extra,
        ), null);
    }
}
