<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Contracts\Documents\PdfRenderer;
use App\Data\Audit\AuditEntry;
use App\Data\Documents\RenderedDocument;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\Locale;
use App\Exceptions\Files\FileRejected;
use App\Exceptions\Reports\FeedbackReportRejected;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Reports\FeedbackReportDocumentBuilder;

final readonly class RenderFeedbackReportPdf
{
    public function __construct(
        private GetFeedbackReport $reports,
        private FeedbackReportDocumentBuilder $documents,
        private PdfRenderer $renderer,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, Assessment $assessment, Locale $locale, int $boundUserId): RenderedDocument
    {
        if ($boundUserId !== $actor->id) {
            throw FileRejected::signatureInvalid();
        }

        $report = $this->reports->handle($assessment);

        if (! $report->isAvailable()) {
            throw FeedbackReportRejected::notAvailable();
        }

        $pdf = $this->renderer->render($this->documents->for($report, $locale));

        $this->audit->record(new AuditEntry(AuditEvent::FeedbackReportExported, $assessment, [], [
            'locale' => $locale->value,
            'result_version' => $report->result->snapshot?->version,
        ], ['download']));

        return new RenderedDocument($pdf, $this->documents->fileNameOf($report, $locale));
    }
}
