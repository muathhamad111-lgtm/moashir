<?php

declare(strict_types=1);

namespace App\Listeners\Certificates;

use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Certificates\CertificateIssued;
use App\Services\Entities\EntityNotifier;
use App\Support\Assessments\AssessmentLinks;
use App\Support\Assessments\RequestNotices;
use App\Support\Certificates\CertificateLinks;

final readonly class NotifyCertificateIssued
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(CertificateIssued $event): void
    {
        $certificate = $event->certificate->loadMissing('assessment.entity');
        $assessment = $certificate->assessment;
        $extra = ['certificate' => $certificate->number];

        $this->notifier->toOwner($assessment->entity, RequestNotices::for(
            NotificationCode::AccreditedCertificateIssued,
            RequestNotices::FOR_ENTITY,
            $assessment,
            CertificateLinks::ENTITY_CERTIFICATES,
            $extra,
        ));
        $this->notifier->toRole(RoleKey::SystemAdmin, RequestNotices::for(
            NotificationCode::AccreditedCertificateIssued,
            RequestNotices::FOR_STAFF,
            $assessment,
            AssessmentLinks::request($assessment->id),
            $extra,
        ), $certificate->issued_by_user_id);
    }
}
