<?php

declare(strict_types=1);

namespace App\Support\Cms\MetaSchemas;

final class HeadingOnlyMetaSchema implements SectionMetaSchema
{
    public function sectionFields(): array
    {
        return HeadingFields::with();
    }

    public function itemFields(): array
    {
        return [];
    }
}
