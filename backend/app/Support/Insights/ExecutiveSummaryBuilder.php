<?php

declare(strict_types=1);

namespace App\Support\Insights;

use App\Data\Assessments\FeedbackSheet;
use App\Data\Insights\ClassifiedSub;
use App\Data\Insights\ExecutiveHighlights;
use App\Data\Insights\QuotedItem;
use App\Data\Insights\StrengthsGaps;
use App\Enums\Assessments\FeedbackKind;
use App\Enums\Certificates\CertificateStatus;
use App\Enums\Insights\CertificateStanding;
use App\Models\Certificates\Certificate;

final class ExecutiveSummaryBuilder
{
    public const int HIGHLIGHTS = 3;

    public function highlights(StrengthsGaps $classes, FeedbackSheet $feedback): ExecutiveHighlights
    {
        $priorities = array_slice([...$classes->criticalGaps, ...$classes->opportunities], 0, self::HIGHLIGHTS);

        return new ExecutiveHighlights(
            array_map(static fn (ClassifiedSub $item): QuotedItem => new QuotedItem($item, $feedback->bodyOf($item->sub->code, FeedbackKind::Strength)), array_slice($classes->strengths, 0, self::HIGHLIGHTS)),
            array_map(static fn (ClassifiedSub $item): QuotedItem => new QuotedItem($item, $feedback->bodyOf($item->sub->code, FeedbackKind::Improvement)), $priorities),
        );
    }

    public function standingOf(?Certificate $certificate): CertificateStanding
    {
        if (! $certificate instanceof Certificate || $certificate->status !== CertificateStatus::Issued) {
            return CertificateStanding::NotIssued;
        }

        return $certificate->level === null ? CertificateStanding::Participation : CertificateStanding::Level;
    }
}
