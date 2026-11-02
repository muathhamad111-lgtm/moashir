<?php

declare(strict_types=1);

namespace App\Enums\Assessments;

enum CompletionItemKind: string
{
    case Cv = 'cv';
    case Evidence = 'evidence';
}
