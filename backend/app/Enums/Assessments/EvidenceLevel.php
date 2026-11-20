<?php

declare(strict_types=1);

namespace App\Enums\Assessments;

/**
 * The criteria level an evidence file belongs to. Indicator attachments are stored under their sub-criterion.
 */
enum EvidenceLevel: string
{
    case Main = 'main';
    case Sub = 'sub';
}
