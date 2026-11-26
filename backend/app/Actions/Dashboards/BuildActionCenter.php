<?php

declare(strict_types=1);

namespace App\Actions\Dashboards;

use App\Contracts\Dashboards\ActionItemSource;
use App\Data\Dashboards\ActionItem;
use App\Models\Identity\User;
use App\Services\Dashboards\ActionItemSources;

final readonly class BuildActionCenter
{
    public function __construct(private ActionItemSources $sources) {}

    /**
     * @return list<ActionItem>
     */
    public function handle(User $user): array
    {
        $items = array_merge(...array_map(
            static fn (ActionItemSource $source): array => $source->itemsFor($user),
            $this->sources->availableTo($user),
        ));

        usort($items, static fn (ActionItem $first, ActionItem $second): int => [$first->severity->rank(), $first->dueAt?->getTimestamp() ?? PHP_INT_MAX]
            <=> [$second->severity->rank(), $second->dueAt?->getTimestamp() ?? PHP_INT_MAX]);

        return $items;
    }
}
