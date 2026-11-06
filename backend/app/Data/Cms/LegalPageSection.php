<?php

declare(strict_types=1);

namespace App\Data\Cms;

use App\Support\ValueObjects\LocalizedContent;

final readonly class LegalPageSection
{
    public function __construct(
        public string $key,
        public ?LocalizedContent $title,
        public ?LocalizedContent $description,
    ) {}
}
