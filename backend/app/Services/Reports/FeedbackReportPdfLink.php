<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Data\Files\SignedUrl;
use App\Enums\Identity\Locale;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Routing\UrlGenerator;

final readonly class FeedbackReportPdfLink
{
    public const string ROUTE = 'assessments.feedback-report.pdf';

    public function __construct(private UrlGenerator $urls, private Repository $config) {}

    public function issue(User $viewer, Assessment $assessment, Locale $locale): SignedUrl
    {
        $expiresAt = CarbonImmutable::now()->addMinutes($this->config->integer('moashir.documents.download_ttl_minutes'));

        return new SignedUrl($this->urls->temporarySignedRoute(self::ROUTE, $expiresAt, [
            'assessment' => $assessment->id,
            'locale' => $locale->value,
            'user' => $viewer->id,
        ]), $expiresAt);
    }
}
