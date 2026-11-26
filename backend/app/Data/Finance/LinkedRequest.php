<?php

declare(strict_types=1);

namespace App\Data\Finance;

use App\Data\Assessments\SubmissionProgress;

final readonly class LinkedRequest
{
    public function __construct(
        public OpenedRequest $request,
        public string $status,
        public bool $isOpenable,
        public ?SubmissionProgress $progress,
    ) {}
}
