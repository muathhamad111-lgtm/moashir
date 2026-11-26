<?php

declare(strict_types=1);

namespace App\Support\Cms\MetaSchemas;

use App\Enums\Assessments\AssessmentPath;
use App\Support\Cms\Meta\ChoiceMetaField;
use App\Support\Cms\Meta\LocalizedMetaField;

final class PathsMetaSchema implements SectionMetaSchema
{
    public function sectionFields(): array
    {
        return HeadingFields::with(
            LocalizedMetaField::label('idea_label'),
            LocalizedMetaField::label('cost_label'),
            LocalizedMetaField::label('free_label'),
        );
    }

    public function itemFields(): array
    {
        return [
            new ChoiceMetaField('path', AssessmentPath::cases()),
            LocalizedMetaField::note('cost_note'),
        ];
    }
}
