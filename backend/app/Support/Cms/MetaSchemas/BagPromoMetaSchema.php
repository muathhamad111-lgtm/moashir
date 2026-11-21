<?php

declare(strict_types=1);

namespace App\Support\Cms\MetaSchemas;

use App\Enums\Cms\CmsItemRole;
use App\Enums\Cms\LandingGlyph;
use App\Support\Cms\Meta\ChoiceMetaField;
use App\Support\Cms\Meta\LocalizedMetaField;

final class BagPromoMetaSchema implements SectionMetaSchema
{
    public function sectionFields(): array
    {
        return HeadingFields::with(
            LocalizedMetaField::label('notice_title'),
            LocalizedMetaField::note('notice_text'),
            LocalizedMetaField::label('how_label'),
            LocalizedMetaField::note('how_text'),
        );
    }

    public function itemFields(): array
    {
        return [
            new ChoiceMetaField('role', [CmsItemRole::Step, CmsItemRole::Tool]),
            new ChoiceMetaField('glyph', LandingGlyph::cases()),
        ];
    }
}
