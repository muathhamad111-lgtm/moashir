<?php

declare(strict_types=1);

namespace App\Actions\Insights;

use App\Contracts\Documents\PdfRenderer;
use App\Data\Audit\AuditEntry;
use App\Data\Documents\RenderedDocument;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\Locale;
use App\Exceptions\Files\FileRejected;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Insights\BoardReportDocumentBuilder;

final readonly class RenderBoardReportPdf
{
    public function __construct(
        private GetExecutiveSummary $summaries,
        private BoardReportDocumentBuilder $documents,
        private PdfRenderer $renderer,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, Assessment $assessment, Locale $locale, int $boundUserId): RenderedDocument
    {
        if ($boundUserId !== $actor->id) {
            throw FileRejected::signatureInvalid();
        }

        $summary = $this->summaries->handle($assessment);
        $pdf = $this->renderer->render($this->documents->for($summary, $locale));
        $rules = $summary->subject->rules;

        $this->audit->record(new AuditEntry(AuditEvent::BoardReportExported, $assessment, [], [
            'assessment_id' => $assessment->id,
            'snapshot_version' => $summary->subject->result->snapshot()->version,
            'locale' => $locale->value,
            'gap_thresholds_version' => $rules->gaps->version,
            'maturity_bands_version' => $rules->bands->version,
        ], ['download']));

        return new RenderedDocument($pdf, $this->documents->fileNameOf($summary, $locale));
    }
}
