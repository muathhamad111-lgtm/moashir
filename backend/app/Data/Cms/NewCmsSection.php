<?php

declare(strict_types=1);

namespace App\Data\Cms;

use App\Enums\Cms\CmsSectionType;

final readonly class NewCmsSection
{
    public function __construct(
        public string $key,
        public CmsSectionType $type,
        public bool $isEnabled,
        public CmsContentChanges $content,
    ) {}
}
