<?php

declare(strict_types=1);

namespace App\Enums\Cms;

enum SocialPlatform: string
{
    case X = 'x';
    case Linkedin = 'linkedin';
    case Youtube = 'youtube';
    case Instagram = 'instagram';
    case Facebook = 'facebook';
}
