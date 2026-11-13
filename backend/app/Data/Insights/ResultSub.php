<?php

declare(strict_types=1);

namespace App\Data\Insights;

use App\Support\ValueObjects\LocalizedText;

final readonly class ResultSub
{
    public function __construct(
        public string $code,
        public ?LocalizedText $name,
        public ResultFigures $figures,
        public int $position,
    ) {}
}
