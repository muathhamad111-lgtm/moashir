<?php

declare(strict_types=1);

namespace App\Enums\Integrations;

enum AnthropicInferenceGeo: string
{
    case Global = 'global';
    case Us = 'us';
}
