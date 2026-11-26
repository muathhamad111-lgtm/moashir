<?php

declare(strict_types=1);

namespace App\Support\Cms\MetaSchemas;

final class UnstructuredMetaSchema implements SectionMetaSchema
{
    public function sectionFields(): array
    {
        return [];
    }

    public function itemFields(): array
    {
        return [];
    }
}
