<?php

declare(strict_types=1);

namespace App\Data\Kpis;

final readonly class KpiCatalogEntry
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(public array $attributes) {}
}
