<?php

declare(strict_types=1);

namespace App\Data\Files;

final readonly class ImageChange
{
    public function __construct(public ?string $fileId) {}

    public function removes(): bool
    {
        return $this->fileId === null;
    }
}
