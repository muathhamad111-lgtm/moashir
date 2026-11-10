<?php

declare(strict_types=1);

namespace App\Data\Paths;

final readonly class DraftProgress
{
    public function __construct(public int $subsComplete, public int $subsTotal) {}

    /**
     * @return array{subs_complete: int, subs_total: int}
     */
    public function toArray(): array
    {
        return ['subs_complete' => $this->subsComplete, 'subs_total' => $this->subsTotal];
    }
}
