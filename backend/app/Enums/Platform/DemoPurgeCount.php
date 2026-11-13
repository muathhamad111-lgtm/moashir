<?php

declare(strict_types=1);

namespace App\Enums\Platform;

enum DemoPurgeCount: string
{
    case RegisterRecords = 'register_records';
    case Entities = 'entities';
    case UsersDeleted = 'users_deleted';
    case UsersRetired = 'users_retired';
    case Assessments = 'assessments';
    case Certificates = 'certificates';
    case Payments = 'payments';
    case AwardCycles = 'award_cycles';
    case Files = 'files';
}
