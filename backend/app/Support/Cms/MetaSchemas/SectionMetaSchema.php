<?php

declare(strict_types=1);

namespace App\Support\Cms\MetaSchemas;

use App\Support\Cms\Meta\MetaField;

interface SectionMetaSchema
{
    /**
     * @return list<MetaField>
     */
    public function sectionFields(): array;

    /**
     * @return list<MetaField>
     */
    public function itemFields(): array;
}
