<?php

declare(strict_types=1);

namespace App\Actions\Insights;

use App\Data\Files\SignedDownload;
use App\Enums\Identity\Locale;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Insights\BoardReportDocumentBuilder;
use App\Services\Insights\InsightsSubjectLoader;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Routing\UrlGenerator;

final readonly class IssueBoardReportExport
{
    public const string ROUTE = 'assessments.board-report.pdf';

    public function __construct(
        private InsightsSubjectLoader $subjects,
        private BoardReportDocumentBuilder $documents,
        private UrlGenerator $urls,
        private Repository $config,
    ) {}

    public function handle(User $actor, Assessment $assessment, Locale $locale): SignedDownload
    {
        $result = $this->subjects->eligibleResultOf($assessment);
        $expiresAt = CarbonImmutable::now()->addMinutes($this->config->integer('moashir.documents.download_ttl_minutes'));
        $url = $this->urls->temporarySignedRoute(self::ROUTE, $expiresAt, [
            'assessment' => $assessment->id,
            'locale' => $locale->value,
            'user' => $actor->id,
        ]);

        return new SignedDownload($url, $expiresAt, $this->documents->fileNameFor($result->assessment->request_number, $locale));
    }
}
