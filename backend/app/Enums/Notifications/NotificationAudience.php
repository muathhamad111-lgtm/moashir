<?php

declare(strict_types=1);

namespace App\Enums\Notifications;

use App\Enums\Identity\RoleKey;

enum NotificationAudience: string
{
    case AccountHolder = 'account_holder';
    case SuperAdmin = 'super_admin';
    case SystemAdmin = 'system_admin';
    case CriteriaOfficer = 'criteria_officer';
    case RequestFollower = 'request_follower';
    case Assessor = 'assessor';
    case CommitteeAssessor = 'committee_assessor';
    case FilteringOfficer = 'filtering_officer';
    case AwardSecretary = 'award_secretary';
    case ContentOfficer = 'content_officer';
    case FinanceOfficer = 'finance_officer';
    case Entity = 'entity';
    case ContactHandler = 'contact_handler';

    public function roleKey(): ?RoleKey
    {
        return match ($this) {
            self::AccountHolder, self::SuperAdmin, self::ContactHandler => null,
            self::CommitteeAssessor => RoleKey::Assessor,
            default => RoleKey::from($this->value),
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
