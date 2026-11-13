<?php

declare(strict_types=1);

namespace App\Data\Cms;

final readonly class NewCmsItem
{
    public function __construct(public bool $isEnabled, public CmsContentChanges $content) {}
}
