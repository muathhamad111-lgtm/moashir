<?php

declare(strict_types=1);

namespace App\Data\Nda;

final readonly class NdaRegisterSummary
{
    public function __construct(public int $inScope, public int $accepted) {}

    public function pending(): int
    {
        return max(0, $this->inScope - $this->accepted);
    }

    /**
     * @return array{in_scope: int, accepted: int, pending: int}
     */
    public function toArray(): array
    {
        return ['in_scope' => $this->inScope, 'accepted' => $this->accepted, 'pending' => $this->pending()];
    }
}
