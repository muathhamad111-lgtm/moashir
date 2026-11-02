<?php

declare(strict_types=1);

namespace App\Data\Award;

final readonly class CommitteeDistribution
{
    /**
     * @param  array<int, int>  $countsByUser
     */
    public function __construct(public int $cycleId, public array $countsByUser = []) {}

    public function merge(self $other): self
    {
        $counts = $this->countsByUser;

        foreach ($other->countsByUser as $userId => $count) {
            $counts[$userId] = ($counts[$userId] ?? 0) + $count;
        }

        return new self($this->cycleId, $counts);
    }

    public function isEmpty(): bool
    {
        return $this->countsByUser === [];
    }
}
