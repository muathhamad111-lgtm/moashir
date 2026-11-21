<?php

declare(strict_types=1);

namespace App\Data\Watermark;

use App\Support\ValueObjects\PageSize;

final readonly class StampSheet
{
    /**
     * @param  list<PageSize>  $sizes
     */
    public function __construct(
        public string $html,
        public array $sizes,
        public string $caption,
    ) {}
}
