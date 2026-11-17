<?php

declare(strict_types=1);

namespace App\Support\Cms\MetaSchemas;

use App\Support\Cms\Meta\LocalizedMetaField;
use App\Support\Cms\Meta\MetaField;

final class HeadingFields
{
    /**
     * @return list<MetaField>
     */
    public static function with(MetaField ...$extra): array
    {
        return [LocalizedMetaField::label('eyebrow'), LocalizedMetaField::label('highlight'), ...array_values($extra)];
    }
}
