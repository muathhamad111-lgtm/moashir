<?php

declare(strict_types=1);

namespace App\Data\Insights;

use App\Enums\Insights\ResultIneligibility;

final readonly class ReportLibraryRow
{
    public function __construct(public ResultCandidate $result, public ?ResultIneligibility $ineligibility, public ?string $feedbackPdfUrl) {}
}
