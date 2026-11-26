<?php

declare(strict_types=1);

namespace App\Support\Mail;

use App\Data\Entities\EntityNotice;
use App\Data\Mail\AccreditedProgress;
use App\Data\Mail\CertificateSeal;
use App\Data\Mail\MailBlocks;
use App\Enums\Certificates\CertificateLevel;
use App\Enums\Identity\Locale;
use App\Enums\Notifications\AccreditedJourneyStage;
use App\Enums\Notifications\NotificationCode;

/**
 * The blocks of the preview samples, so the admin preview shows what the real e-mail carries: the steps of a
 * submitted request (ACC-03), the seal of an issued certificate (ACC-09) and the items of a completion request.
 */
final class SampleMailBlocks
{
    public static function for(NotificationCode $code, string $prefix, Locale $locale): MailBlocks
    {
        $blocks = new MailBlocks(SampleRequestedItems::for($code, $locale));

        if (! str_ends_with($prefix, '.'.EntityNotice::FOR_ENTITY)) {
            return $blocks;
        }

        return match ($code) {
            NotificationCode::AccreditedSubmitted => $blocks->withProgress(new AccreditedProgress(AccreditedJourneyStage::Assignment)),
            NotificationCode::AccreditedCertificateIssued => $blocks->withSeal(new CertificateSeal(CertificateLevel::Gold)),
            default => $blocks,
        };
    }
}
