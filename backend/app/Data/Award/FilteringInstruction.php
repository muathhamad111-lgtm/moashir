<?php

declare(strict_types=1);

namespace App\Data\Award;

final readonly class FilteringInstruction
{
    public function __construct(public ?string $note = null, public ?string $decisionReason = null, public ?string $reason = null) {}
}
