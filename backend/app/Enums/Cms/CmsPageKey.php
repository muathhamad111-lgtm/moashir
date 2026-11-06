<?php

declare(strict_types=1);

namespace App\Enums\Cms;

enum CmsPageKey: string
{
    case Home = 'home';
    case AccreditedIntro = 'accredited_intro';
    case AdvancedIntro = 'advanced_intro';
    case BagDetails = 'bag_details';
    case Lenses = 'lenses';
    case Kpis = 'kpis';
    case Accessibility = 'accessibility';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
