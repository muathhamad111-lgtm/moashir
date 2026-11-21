<?php

declare(strict_types=1);

namespace App\Data\Insights;

use App\Enums\Insights\MaturityBand;
use App\Support\ValueObjects\LocalizedText;

final readonly class MaturityTextEdit
{
    public function __construct(public MaturityBand $band, public ?string $mainCriterionCode, public LocalizedText $text) {}
}
