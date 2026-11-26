<?php

declare(strict_types=1);

namespace App\Data\Dashboards;

final readonly class BoardContent
{
    /**
     * @param  list<DashboardCard>  $cards
     * @param  list<DashboardWidget>  $widgets
     */
    public function __construct(public array $cards, public array $widgets) {}
}
