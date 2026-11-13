<?php

declare(strict_types=1);

namespace App\Enums\Identity;

enum BlindIndexPurpose: string
{
    case Phone = 'phone';
    case SignInIdentifier = 'sign_in_identifier';
}
