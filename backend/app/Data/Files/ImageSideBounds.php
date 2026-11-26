<?php

declare(strict_types=1);

namespace App\Data\Files;

final readonly class ImageSideBounds
{
    public function __construct(public int $minimum, public int $maximum) {}
}
