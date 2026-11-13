<?php

declare(strict_types=1);

namespace App\Support\Demo;

use App\Enums\Platform\DemoPurgeCount;

final class DemoPurgeTally
{
    /**
     * @var array<string, int>
     */
    private array $counts = [];

    public function __construct()
    {
        foreach (DemoPurgeCount::cases() as $count) {
            $this->counts[$count->value] = 0;
        }
    }

    public function add(DemoPurgeCount $count, int $rows): void
    {
        $this->counts[$count->value] += $rows;
    }

    public function isEmpty(): bool
    {
        return array_sum($this->counts) === 0;
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return $this->counts;
    }
}
