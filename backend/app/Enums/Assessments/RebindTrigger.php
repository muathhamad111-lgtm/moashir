<?php

declare(strict_types=1);

namespace App\Enums\Assessments;

enum RebindTrigger: string
{
    case CriteriaEdit = 'criteria_edit';
    case EntityRestore = 'entity_restore';
}
