<?php

declare(strict_types=1);

namespace App\Data\Dashboards;

use App\Enums\Dashboards\CardFormat;

final readonly class DashboardCard
{
    public function __construct(
        public string $key,
        public int|string|null $value,
        public CardFormat $format = CardFormat::Count,
        public ?string $link = null,
    ) {}
}
