<?php

declare(strict_types=1);

namespace App\Enums\Entities;

use App\Support\ValueObjects\LocalizedText;

enum EntityRequestPath: string
{
    case SelfAssessment = 'self';
    case Accredited = 'accredited';
    case Advanced = 'advanced';
    case Bag = 'bag';

    public function label(): LocalizedText
    {
        return LocalizedText::fromTranslation('assessments.paths.'.$this->value);
    }
}
