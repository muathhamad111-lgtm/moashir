<?php

declare(strict_types=1);

namespace App\Support\Cms\MetaSchemas;

use App\Enums\Cms\CmsItemRole;
use App\Enums\Cms\CmsSectionVariant;
use App\Support\Cms\Meta\ChoiceMetaField;

final class RichTextMetaSchema implements SectionMetaSchema
{
    public function sectionFields(): array
    {
        return HeadingFields::with(new ChoiceMetaField('variant', [CmsSectionVariant::Cta, CmsSectionVariant::Band]));
    }

    public function itemFields(): array
    {
        return [new ChoiceMetaField('role', [CmsItemRole::SecondaryCta])];
    }
}
