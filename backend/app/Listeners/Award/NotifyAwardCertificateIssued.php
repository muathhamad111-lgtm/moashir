<?php

declare(strict_types=1);

namespace App\Listeners\Award;

use App\Enums\Certificates\CertificateKind;
use App\Enums\Notifications\NotificationCode;
use App\Events\Certificates\CertificateIssued;
use App\Models\Award\AwardCycle;
use App\Services\Entities\EntityNotifier;
use App\Support\Award\AwardNotices;
use App\Support\Certificates\CertificateLinks;

/**
 * Tells the entity its award certificate is ready (ADV-19 for a winner or qualified certificate, ADV-32 for the
 * participation one). Only the entity is told: the certificate is its own, and staff see it on the request.
 */
final readonly class NotifyAwardCertificateIssued
{
    public function __construct(private EntityNotifier $notifier) {}

    public function handle(CertificateIssued $event): void
    {
        $certificate = $event->certificate;

        if (! $certificate->kind->isAward()) {
            return;
        }

        $assessment = $certificate->loadMissing('assessment.entity', 'assessment.awardCycle')->assessment;
        $cycle = $assessment->awardCycle;

        if (! $cycle instanceof AwardCycle) {
            return;
        }

        $code = $certificate->kind === CertificateKind::AdvancedParticipation
            ? NotificationCode::AdvancedParticipationCertificateIssued
            : NotificationCode::AdvancedCertificateIssued;

        $this->notifier->toOwner($assessment->entity, AwardNotices::for($code, AwardNotices::FOR_ENTITY, $cycle, CertificateLinks::ENTITY_CERTIFICATES, [
            'number' => $assessment->request_number,
            'certificate' => $certificate->number,
        ]));
    }
}
