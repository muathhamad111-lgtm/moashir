<?php

declare(strict_types=1);

namespace App\Support\Cms\MetaSchemas;

use App\Enums\Cms\CmsSectionVariant;
use App\Enums\Cms\LandingPathTag;
use App\Enums\Cms\LandingScreen;
use App\Support\Cms\Meta\ChoiceListMetaField;
use App\Support\Cms\Meta\ChoiceMetaField;
use App\Support\Cms\Meta\LocalizedMetaField;

final class HowItWorksMetaSchema implements SectionMetaSchema
{
    public function sectionFields(): array
    {
        return HeadingFields::with(
            new ChoiceMetaField('variant', [CmsSectionVariant::Journey]),
            LocalizedMetaField::label('step_label'),
            LocalizedMetaField::label('tag_all'),
            LocalizedMetaField::label('tag_accredited'),
            LocalizedMetaField::label('tag_award'),
        );
    }

    public function itemFields(): array
    {
        return [
            new ChoiceListMetaField('tags', LandingPathTag::cases()),
            new ChoiceMetaField('screen', LandingScreen::cases()),
        ];
    }
}
