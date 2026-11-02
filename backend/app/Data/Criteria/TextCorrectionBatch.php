<?php

declare(strict_types=1);

namespace App\Data\Criteria;

use App\Support\ValueObjects\LocalizedText;

final readonly class TextCorrectionBatch
{
    /**
     * @param  list<TextCorrection>  $corrections
     */
    public function __construct(
        public ?LocalizedText $label,
        public array $corrections,
        public ?string $reason,
    ) {}
}
