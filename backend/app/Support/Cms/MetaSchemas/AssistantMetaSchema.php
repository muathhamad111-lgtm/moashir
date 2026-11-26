<?php

declare(strict_types=1);

namespace App\Support\Cms\MetaSchemas;

use App\Support\Cms\Meta\LocalizedMetaField;

final class AssistantMetaSchema implements SectionMetaSchema
{
    public function sectionFields(): array
    {
        return HeadingFields::with(LocalizedMetaField::label('input_label'), LocalizedMetaField::note('fallback_reply'));
    }

    public function itemFields(): array
    {
        return [];
    }
}
