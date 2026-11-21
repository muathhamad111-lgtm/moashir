<?php

declare(strict_types=1);

namespace App\Support\Cms\MetaSchemas;

use App\Enums\Cms\LandingGlyph;
use App\Support\Cms\Meta\ChoiceMetaField;

final class ExcellenceModelMetaSchema implements SectionMetaSchema
{
    public function sectionFields(): array
    {
        return HeadingFields::with();
    }

    public function itemFields(): array
    {
        return [new ChoiceMetaField('glyph', LandingGlyph::cases())];
    }
}
