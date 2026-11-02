<?php

declare(strict_types=1);

namespace App\Data\Award;

use App\Enums\Award\CommitteeDiscussionMode;
use Carbon\CarbonImmutable;

final readonly class DiscussionInstruction
{
    public function __construct(public CommitteeDiscussionMode $mode, public ?CarbonImmutable $deadlineAt, public ?string $agreedOutcome, public ?string $reason) {}
}
