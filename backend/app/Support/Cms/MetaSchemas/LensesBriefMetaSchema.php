<?php

declare(strict_types=1);

namespace App\Support\Cms\MetaSchemas;

use App\Support\Cms\Meta\LocalizedMetaField;

final class LensesBriefMetaSchema implements SectionMetaSchema
{
    public function sectionFields(): array
    {
        return HeadingFields::with(LocalizedMetaField::note('note'));
    }

    public function itemFields(): array
    {
        return [];
    }
}
