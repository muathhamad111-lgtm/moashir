<?php

declare(strict_types=1);

namespace App\Enums\Integrations;

enum OpenAiRegion: string
{
    case Global = 'global';
    case Us = 'us';
    case Eu = 'eu';
    case Ae = 'ae';
    case Au = 'au';
    case Ca = 'ca';
    case Jp = 'jp';
    case In = 'in';
    case Sg = 'sg';
    case Kr = 'kr';
    case Gb = 'gb';

    public function host(): string
    {
        return $this === self::Global ? 'api.openai.com' : $this->value.'.api.openai.com';
    }
}
