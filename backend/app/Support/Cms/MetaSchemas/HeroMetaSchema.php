<?php

declare(strict_types=1);

namespace App\Support\Cms\MetaSchemas;

use App\Enums\Cms\CmsItemRole;
use App\Support\Cms\Meta\ChoiceMetaField;
use App\Support\Cms\Meta\LocalizedMetaField;

final class HeroMetaSchema implements SectionMetaSchema
{
    public function sectionFields(): array
    {
        return HeadingFields::with(LocalizedMetaField::label('score_caption'));
    }

    public function itemFields(): array
    {
        return [new ChoiceMetaField('role', [CmsItemRole::Point, CmsItemRole::SecondaryCta])];
    }
}
