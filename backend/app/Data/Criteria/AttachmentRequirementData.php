<?php

declare(strict_types=1);

namespace App\Data\Criteria;

use App\Support\ValueObjects\LocalizedText;

final readonly class AttachmentRequirementData
{
    public function __construct(
        public ?string $key,
        public LocalizedText $name,
        public bool $isRequired,
    ) {}
}
