<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Data\Files\SignedDownload;
use App\Enums\Identity\Locale;
use App\Exceptions\Reports\FeedbackReportRejected;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Reports\FeedbackReportDocumentBuilder;
use App\Services\Reports\FeedbackReportPdfLink;

final readonly class IssueFeedbackReportExport
{
    public function __construct(
        private GetFeedbackReport $reports,
        private FeedbackReportDocumentBuilder $documents,
        private FeedbackReportPdfLink $links,
    ) {}

    public function handle(User $actor, Assessment $assessment, Locale $locale): SignedDownload
    {
        $report = $this->reports->handle($assessment);

        if (! $report->isAvailable()) {
            throw FeedbackReportRejected::notAvailable();
        }

        $link = $this->links->issue($actor, $assessment, $locale);

        return new SignedDownload($link->url, $link->expiresAt, $this->documents->fileNameOf($report, $locale));
    }
}
