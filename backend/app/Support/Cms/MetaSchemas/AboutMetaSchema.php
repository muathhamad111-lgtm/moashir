<?php

declare(strict_types=1);

namespace App\Support\Cms\MetaSchemas;

use App\Enums\Cms\CmsItemRole;
use App\Enums\Cms\LandingGlyph;
use App\Support\Cms\Meta\ChoiceMetaField;
use App\Support\Cms\Meta\LocalizedMetaField;

final class AboutMetaSchema implements SectionMetaSchema
{
    private const int KEY_WORDS_MAX = 300;

    public function sectionFields(): array
    {
        return HeadingFields::with(
            new LocalizedMetaField('key_words', self::KEY_WORDS_MAX),
            LocalizedMetaField::label('audience_label'),
            LocalizedMetaField::label('goals_title'),
        );
    }

    public function itemFields(): array
    {
        return [
            new ChoiceMetaField('role', [CmsItemRole::Audience, CmsItemRole::Goal]),
            new ChoiceMetaField('glyph', LandingGlyph::cases()),
        ];
    }
}
