<?php

declare(strict_types=1);

namespace App\Support\Cms\MetaSchemas;

use App\Enums\Cms\CmsSectionType;

final class SectionMetaSchemas
{
    public static function of(?CmsSectionType $type): SectionMetaSchema
    {
        return match ($type) {
            CmsSectionType::Hero => new HeroMetaSchema,
            CmsSectionType::About => new AboutMetaSchema,
            CmsSectionType::ExcellenceModel => new ExcellenceModelMetaSchema,
            CmsSectionType::Paths => new PathsMetaSchema,
            CmsSectionType::HowItWorks => new HowItWorksMetaSchema,
            CmsSectionType::RichText => new RichTextMetaSchema,
            CmsSectionType::Levels => new LevelsMetaSchema,
            CmsSectionType::BagPromo => new BagPromoMetaSchema,
            CmsSectionType::LensesBrief => new LensesBriefMetaSchema,
            CmsSectionType::LeadershipPromo => new LeadershipPromoMetaSchema,
            CmsSectionType::Contact => new ContactMetaSchema,
            CmsSectionType::Assistant => new AssistantMetaSchema,
            CmsSectionType::KpiPromo, CmsSectionType::Partners => new HeadingOnlyMetaSchema,
            default => new UnstructuredMetaSchema,
        };
    }
}
