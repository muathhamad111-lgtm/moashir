<?php

declare(strict_types=1);

namespace App\Enums\Assessments;

enum ReviewNoteAuthorRole: string
{
    case SuperAdmin = 'super_admin';
    case Assessor = 'assessor';
    case Follower = 'follower';
    case Admin = 'admin';
}
