<?php

declare(strict_types=1);

namespace App\Contracts\Dashboards;

use App\Data\Dashboards\ActionItem;
use App\Models\Identity\User;

interface ActionItemSource
{
    public function isAvailableTo(User $user): bool;

    /**
     * @return list<ActionItem>
     */
    public function itemsFor(User $user): array;
}
