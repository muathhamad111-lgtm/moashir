<?php

declare(strict_types=1);

namespace App\Data\Dashboards;

final readonly class DashboardWidget
{
    /**
     * @param  array<array-key, mixed>|null  $data
     */
    public function __construct(public string $key, public ?array $data) {}
}
