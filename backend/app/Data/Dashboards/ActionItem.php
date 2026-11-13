<?php

declare(strict_types=1);

namespace App\Data\Dashboards;

use App\Enums\Dashboards\ActionSeverity;
use Carbon\CarbonInterface;

final readonly class ActionItem
{
    public function __construct(
        public string $key,
        public int $count,
        public ActionSeverity $severity,
        public string $link,
        public ?CarbonInterface $dueAt = null,
        public ?ActionItemContext $context = null,
    ) {}
}
