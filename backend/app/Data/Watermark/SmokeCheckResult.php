<?php

declare(strict_types=1);

namespace App\Data\Watermark;

final readonly class SmokeCheckResult
{
    public function __construct(public int $pages, public float $seconds) {}
}
