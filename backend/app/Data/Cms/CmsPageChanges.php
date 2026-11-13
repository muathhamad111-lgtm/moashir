<?php

declare(strict_types=1);

namespace App\Data\Cms;

final readonly class CmsPageChanges
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(public array $attributes) {}

    public function isEmpty(): bool
    {
        return $this->attributes === [];
    }
}
