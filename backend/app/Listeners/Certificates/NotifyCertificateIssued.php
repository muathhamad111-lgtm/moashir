<?php

declare(strict_types=1);

namespace App\Listeners\Certificates;

use App\Data\Entities\EntityNotice;
use App\Data\Mail\CertificateSeal;
use App\Enums\Certificates\CertificateKind;
use App\Enums\Certificates\CertificateLevel;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Certificates\CertificateIssued;
use App\Models\Certificates\Certificate;
use App\Services\Entities\EntityNotifier;
use App\Support\Assessments\AssessmentLinks;
use App\Support\Assessments\RequestNotices;
use App\Support\Certificates\CertificateLinks;

final readonly class NotifyCertificateIssued
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(CertificateIssued $event): void
    {
        if ($event->certificate->kind !== CertificateKind::Accredited) {
            return;
        }

        $certificate = $event->certificate->loadMissing('assessment.entity');
        $assessment = $certificate->assessment;
        $extra = ['certificate' => $certificate->number];

        $this->notifier->toOwner($assessment->entity, $this->withSeal($certificate, RequestNotices::for(
            NotificationCode::AccreditedCertificateIssued,
            RequestNotices::FOR_ENTITY,
            $assessment,
            CertificateLinks::ENTITY_CERTIFICATES,
            $extra,
        )));
        $this->notifier->toRole(RoleKey::SystemAdmin, RequestNotices::for(
            NotificationCode::AccreditedCertificateIssued,
            RequestNotices::FOR_STAFF,
            $assessment,
            AssessmentLinks::request($assessment->id),
            $extra,
        ), $certificate->issued_by_user_id);
    }

    private function withSeal(Certificate $certificate, EntityNotice $notice): EntityNotice
    {
        return $certificate->level instanceof CertificateLevel ? $notice->withSeal(new CertificateSeal($certificate->level)) : $notice;
    }
}
