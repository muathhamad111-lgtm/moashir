<?php

declare(strict_types=1);

namespace App\Enums\Identity;

enum RoleKey: string
{
    case SystemAdmin = 'system_admin';
    case CriteriaOfficer = 'criteria_officer';
    case RequestFollower = 'request_follower';
    case Assessor = 'assessor';
    case FilteringOfficer = 'filtering_officer';
    case AwardSecretary = 'award_secretary';
    case ContentOfficer = 'content_officer';
    case FinanceOfficer = 'finance_officer';
    case Entity = 'entity';

    public function isStaff(): bool
    {
        return $this !== self::Entity;
    }

    public function isAssignable(): bool
    {
        return $this->isStaff();
    }

    public function isCycleScoped(): bool
    {
        return in_array($this, [self::FilteringOfficer, self::AwardSecretary], true);
    }

    public function dashboardPriority(): int
    {
        return match ($this) {
            self::SystemAdmin => 10,
            self::RequestFollower => 20,
            self::Assessor => 30,
            self::FilteringOfficer => 40,
            self::AwardSecretary => 50,
            self::CriteriaOfficer => 60,
            self::ContentOfficer => 70,
            self::FinanceOfficer => 80,
            self::Entity => 90,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<string>
     */
    public static function assignableValues(): array
    {
        return array_values(array_map(
            static fn (self $role): string => $role->value,
            array_filter(self::cases(), static fn (self $role): bool => $role->isAssignable()),
        ));
    }
}
