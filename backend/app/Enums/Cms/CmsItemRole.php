<?php

declare(strict_types=1);

namespace App\Enums\Cms;

enum CmsItemRole: string
{
    case Point = 'point';
    case SecondaryCta = 'secondary-cta';
    case Audience = 'audience';
    case Goal = 'goal';
    case Step = 'step';
    case Tool = 'tool';
}
