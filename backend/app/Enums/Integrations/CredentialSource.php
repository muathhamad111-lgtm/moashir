<?php

declare(strict_types=1);

namespace App\Enums\Integrations;

enum CredentialSource: string
{
    case Settings = 'settings';
    case Environment = 'environment';
    case None = 'none';
}
