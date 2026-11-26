<?php

declare(strict_types=1);

namespace App\Data\Dashboards;

use App\Enums\Dashboards\DashboardBoard;
use Carbon\CarbonInterface;

final readonly class DashboardSummary
{
    public function __construct(
        public DashboardBoard $board,
        public HeldBoards $heldBoards,
        public BoardContent $content,
        public CarbonInterface $generatedAt,
    ) {}
}
