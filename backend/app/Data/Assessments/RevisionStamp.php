<?php

declare(strict_types=1);

namespace App\Data\Assessments;

final readonly class RevisionStamp
{
    public function __construct(public int $authorId, public ?string $reason, public ?int $completionRequestId) {}
}
