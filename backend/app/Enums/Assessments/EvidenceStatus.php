<?php

declare(strict_types=1);

namespace App\Enums\Assessments;

enum EvidenceStatus: string
{
    case Uploaded = 'uploaded';
    case Removed = 'removed';
}
