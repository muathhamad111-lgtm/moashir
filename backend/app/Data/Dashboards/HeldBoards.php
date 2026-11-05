<?php

declare(strict_types=1);

namespace App\Data\Dashboards;

use App\Enums\Dashboards\DashboardBoard;

final readonly class HeldBoards
{
    /**
     * @param  list<DashboardBoard>  $boards
     */
    public function __construct(public array $boards, public DashboardBoard $landing) {}

    public function holds(DashboardBoard $board): bool
    {
        return in_array($board, $this->boards, true);
    }
}
