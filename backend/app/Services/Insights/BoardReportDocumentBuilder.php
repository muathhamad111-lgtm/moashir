<?php

declare(strict_types=1);

namespace App\Services\Insights;

use App\Data\Documents\PdfDocument;
use App\Data\Insights\ExecutiveSummary;
use App\Enums\Documents\DocumentTemplate;
use App\Enums\Documents\DocumentWatermark;
use App\Enums\Identity\Locale;
use App\Services\Reports\EntityLogoImage;
use App\Support\Insights\BoardReportPresenter;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Date;

final readonly class BoardReportDocumentBuilder
{
    public function __construct(private Repository $config, private EntityLogoImage $logos) {}

    public function for(ExecutiveSummary $summary, Locale $locale): PdfDocument
    {
        $assessment = $summary->subject->result->assessment;
        $presenter = new BoardReportPresenter(
            $this->config->integer('moashir.insights.board_report_quote_max_chars'),
            $this->config->integer('moashir.insights.board_report_summary_max_chars'),
            $this->config->string('app.display_timezone'),
        );
        $isTest = $assessment->loadMissing('payment')->payment?->isTest() === true;

        return new PdfDocument(DocumentTemplate::BoardReport->value, [
            ...$presenter->present($summary, Date::now()),
            'entity_logo' => $this->logos->dataUriOf($assessment->entity),
        ], $isTest ? DocumentWatermark::Test->value : null, $locale->value);
    }

    public function fileNameOf(ExecutiveSummary $summary, Locale $locale): string
    {
        return $this->fileNameFor($summary->subject->result->assessment->request_number, $locale);
    }

    public function fileNameFor(string $requestNumber, Locale $locale): string
    {
        return sprintf('board-report-%s-%s.pdf', $requestNumber, $locale->value);
    }
}
