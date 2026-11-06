<?php

declare(strict_types=1);

namespace App\Listeners\Certificates;

use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Certificates\CertificateRevoked;
use App\Services\Entities\EntityNotifier;
use App\Support\Assessments\AssessmentLinks;
use App\Support\Assessments\RequestNotices;
use App\Support\Certificates\CertificateLinks;

final readonly class NotifyCertificateRevoked
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(CertificateRevoked $event): void
    {
        $certificate = $event->certificate->loadMissing('assessment.entity');
        $assessment = $certificate->assessment;

        $this->notifier->toOwner($assessment->entity, RequestNotices::for(
            NotificationCode::CertificateRevoked,
            RequestNotices::FOR_ENTITY,
            $assessment,
            CertificateLinks::ENTITY_CERTIFICATES,
            ['certificate' => $certificate->number, 'reason' => (string) $certificate->revoked_reason],
        ));
        $this->notifier->toRole(RoleKey::SystemAdmin, RequestNotices::for(
            NotificationCode::CertificateRevoked,
            RequestNotices::FOR_STAFF,
            $assessment,
            AssessmentLinks::request($assessment->id),
            ['certificate' => $certificate->number],
        ), $event->actorId);
    }
}
