<?php

declare(strict_types=1);

namespace App\Data\Award;

use App\Enums\Award\SecretaryDecisionKind;

final readonly class SecretaryDecisionDraft
{
    public function __construct(
        public SecretaryDecisionKind $kind,
        public int $cycleId,
        public ?int $assessmentId = null,
        public ?string $note = null,
        public ?int $averageId = null,
        public ?int $assignmentId = null,
        public ?int $regionId = null,
    ) {}
}
