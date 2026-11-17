<?php

declare(strict_types=1);

namespace App\Support\Cms\MetaSchemas;

use App\Support\Cms\Meta\IntegerMetaField;
use App\Support\Cms\Meta\LocalizedMetaField;

final class LevelsMetaSchema implements SectionMetaSchema
{
    private const int SCORE_MAX = 100;

    public function sectionFields(): array
    {
        return HeadingFields::with(
            LocalizedMetaField::label('result_label'),
            LocalizedMetaField::note('hint'),
            LocalizedMetaField::note('verify_note'),
            LocalizedMetaField::note('settings_note'),
            new IntegerMetaField('sample_score', 0, self::SCORE_MAX),
        );
    }

    public function itemFields(): array
    {
        return [];
    }
}
