<?php

declare(strict_types=1);

namespace App\Data\Assessments;

final readonly class ReviewCompletion
{
    public function __construct(
        public ?string $reason = null,
        public bool $issueCertificate = true,
    ) {}
}
