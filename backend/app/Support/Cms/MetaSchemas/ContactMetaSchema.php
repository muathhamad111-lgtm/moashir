<?php

declare(strict_types=1);

namespace App\Support\Cms\MetaSchemas;

use App\Support\Cms\Meta\LocalizedMetaField;

final class ContactMetaSchema implements SectionMetaSchema
{
    public function sectionFields(): array
    {
        return HeadingFields::with(
            LocalizedMetaField::label('email_label'),
            LocalizedMetaField::label('phone_label'),
            LocalizedMetaField::label('address_label'),
            LocalizedMetaField::label('social_label'),
        );
    }

    public function itemFields(): array
    {
        return [];
    }
}
