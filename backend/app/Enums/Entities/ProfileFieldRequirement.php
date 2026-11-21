<?php

declare(strict_types=1);

namespace App\Enums\Entities;

enum ProfileFieldRequirement: string
{
    case Required = 'required';
    case Completion = 'completion';
    case Optional = 'optional';

    public function isCounted(): bool
    {
        return $this !== self::Optional;
    }
}
