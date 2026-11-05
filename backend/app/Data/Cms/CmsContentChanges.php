<?php

declare(strict_types=1);

namespace App\Data\Cms;

use App\Data\Files\ImageChange;

final readonly class CmsContentChanges
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(public array $attributes, public ?ImageChange $image) {}

    public function isEmpty(): bool
    {
        return $this->attributes === [] && ! $this->image instanceof ImageChange;
    }
}
