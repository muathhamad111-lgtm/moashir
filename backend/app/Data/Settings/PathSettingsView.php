<?php

declare(strict_types=1);

namespace App\Data\Settings;

use App\Data\Assessments\UploadLimits;
use App\Data\Finance\PriceQuote;
use App\Enums\Assessments\AssessmentPath;
use App\Support\ValueObjects\PathEntityTexts;

final readonly class PathSettingsView
{
    /**
     * @param  array{min: int, max: int}  $slaLimits
     */
    public function __construct(
        public AssessmentPath $path,
        public int $reviewSlaDays,
        public array $slaLimits,
        public UploadLimits $uploadLimits,
        public bool $isPaid,
        public ?PriceQuote $price,
        public PathEntityTexts $texts,
    ) {}
}
