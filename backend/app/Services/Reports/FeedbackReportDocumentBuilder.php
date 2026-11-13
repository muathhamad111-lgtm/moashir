<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Data\Documents\PdfDocument;
use App\Data\Reports\FeedbackReport;
use App\Enums\Documents\DocumentTemplate;
use App\Enums\Identity\Locale;
use App\Support\Reports\FeedbackReportPresenter;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Date;

final readonly class FeedbackReportDocumentBuilder
{
    public function __construct(private Repository $config) {}

    public function for(FeedbackReport $report, Locale $locale): PdfDocument
    {
        $timezone = $this->config->string('app.display_timezone');
        $generatedAt = Date::now()->setTimezone($timezone);

        return new PdfDocument(DocumentTemplate::FeedbackReport->value, [
            ...FeedbackReportPresenter::present($report, $timezone),
            'generated_on' => $generatedAt->toDateString(),
            'generated_on_hijri' => FeedbackReportPresenter::hijri($generatedAt, $timezone),
        ], null, $locale->value);
    }

    public function fileNameOf(FeedbackReport $report, Locale $locale): string
    {
        return sprintf('feedback-report-%s-%s.pdf', $report->result->assessment->request_number, $locale->value);
    }
}
