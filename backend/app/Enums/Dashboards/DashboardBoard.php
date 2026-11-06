<?php

declare(strict_types=1);

namespace App\Enums\Dashboards;

use App\Enums\Identity\RoleKey;

enum DashboardBoard: string
{
    case SystemAdmin = 'system_admin';
    case AwardSecretary = 'award_secretary';
    case FilteringOfficer = 'filtering_officer';
    case RequestFollower = 'request_follower';
    case Assessor = 'assessor';
    case CommitteeAssessor = 'committee_assessor';
    case ContentOfficer = 'content_officer';
    case CriteriaOfficer = 'criteria_officer';
    case FinanceOfficer = 'finance_officer';
    case Entity = 'entity';
    case Executive = 'executive';

    public static function ofRole(RoleKey $role): self
    {
        return self::from($role->value);
    }

    public function precedence(): int
    {
        return array_search($this, self::cases(), true) ?: 0;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
