<?php

declare(strict_types=1);

namespace App\Enums\Cms;

enum CmsSectionType: string
{
    case Hero = 'hero';
    case Paths = 'paths';
    case ExcellenceModel = 'excellence_model';
    case HowItWorks = 'how_it_works';
    case BagPromo = 'bag_promo';
    case LeadershipPromo = 'leadership_promo';
    case LensesBrief = 'lenses_brief';
    case KpiPromo = 'kpi_promo';
    case Partners = 'partners';
    case Contact = 'contact';
    case Footer = 'footer';
    case Assistant = 'assistant';
    case RichText = 'rich_text';
    case Faq = 'faq';
    case PricingBox = 'pricing_box';
    case CycleBox = 'cycle_box';
    case Winners = 'winners';
    case Logos = 'logos';
}
