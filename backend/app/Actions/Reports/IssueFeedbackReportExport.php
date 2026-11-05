<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Data\Files\SignedDownload;
use App\Enums\Identity\Locale;
use App\Exceptions\Reports\FeedbackReportRejected;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Reports\FeedbackReportDocumentBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Routing\UrlGenerator;

final readonly class IssueFeedbackReportExport
{
    public const string ROUTE = 'assessments.feedback-report.pdf';

    public function __construct(
        private GetFeedbackReport $reports,
        private FeedbackReportDocumentBuilder $documents,
        private UrlGenerator $urls,
        private Repository $config,
    ) {}

    public function handle(User $actor, Assessment $assessment, Locale $locale): SignedDownload
    {
        $report = $this->reports->handle($assessment);

        if (! $report->isAvailable()) {
            throw FeedbackReportRejected::notAvailable();
        }

        $expiresAt = CarbonImmutable::now()->addMinutes($this->config->integer('moashir.documents.download_ttl_minutes'));
        $url = $this->urls->temporarySignedRoute(self::ROUTE, $expiresAt, [
            'assessment' => $assessment->id,
            'locale' => $locale->value,
            'user' => $actor->id,
        ]);

        return new SignedDownload($url, $expiresAt, $this->documents->fileNameOf($report, $locale));
    }
}
