<?php

declare(strict_types=1);

namespace App\Support\Insights;

use App\Data\Insights\ReportLibraryRow;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Certificates\CertificateStatus;
use App\Enums\Insights\ComingSoonReport;
use App\Enums\Insights\ReportLibraryItem;
use App\Enums\Insights\ResultIneligibility;

final class ReportLibraryPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(ReportLibraryRow $row): array
    {
        $assessment = $row->result->assessment;
        $certificate = $row->result->certificate;
        $eligible = ! $row->ineligibility instanceof ResultIneligibility;

        return [
            'result' => ResultRefPresenter::present($row->result),
            'eligible' => $eligible,
            'exclusion_reason' => $row->ineligibility?->value,
            'items' => [
                [
                    ...self::item(ReportLibraryItem::FeedbackReport, $assessment->status === AccreditedAssessmentStatus::Completed),
                    'pdf_available' => $row->feedbackPdfUrl !== null,
                    'pdf_url' => $row->feedbackPdfUrl,
                ],
                self::item(ReportLibraryItem::BoardReport, $eligible),
                [
                    ...self::item(ReportLibraryItem::Certificate, $certificate?->status === CertificateStatus::Issued),
                    'status' => $certificate?->status->value,
                    'certificate_id' => $certificate?->public_id,
                ],
            ],
        ];
    }

    /**
     * @return list<array{kind: string}>
     */
    public static function comingSoon(): array
    {
        return array_map(static fn (ComingSoonReport $report): array => ['kind' => $report->value], ComingSoonReport::cases());
    }

    /**
     * @return array{kind: string, available: bool, status: string|null, certificate_id: string|null}
     */
    private static function item(ReportLibraryItem $kind, bool $available): array
    {
        return ['kind' => $kind->value, 'available' => $available, 'status' => null, 'certificate_id' => null];
    }
}
