<?php

declare(strict_types=1);

namespace App\Support\Cms\MetaSchemas;

use App\Support\Cms\Meta\LocalizedMetaField;

final class LeadershipPromoMetaSchema implements SectionMetaSchema
{
    public function sectionFields(): array
    {
        return HeadingFields::with(LocalizedMetaField::label('badge_label'));
    }

    public function itemFields(): array
    {
        return [];
    }
}
