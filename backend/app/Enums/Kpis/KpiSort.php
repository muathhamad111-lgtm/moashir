<?php

declare(strict_types=1);

namespace App\Enums\Kpis;

enum KpiSort: string
{
    case Order = 'order';
    case NameAscending = 'name';
    case NameDescending = '-name';
    case Newest = '-created_at';
    case Oldest = 'created_at';
    case HighestValue = '-value';
    case LowestValue = 'value';
    case LatestUpdate = '-last_update';
    case EarliestUpdate = 'last_update';
    case RecentlyEdited = '-updated_at';

    /**
     * @return list<self>
     */
    public static function publicSorts(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $sort): bool => $sort !== self::RecentlyEdited));
    }

    /**
     * @return list<self>
     */
    public static function adminSorts(): array
    {
        return [self::Order, self::NameAscending, self::NameDescending, self::RecentlyEdited, self::Newest];
    }

    /**
     * @param  list<self>  $sorts
     * @return list<string>
     */
    public static function valuesOf(array $sorts): array
    {
        return array_map(static fn (self $sort): string => $sort->value, $sorts);
    }

    public function isDescending(): bool
    {
        return str_starts_with($this->value, '-');
    }
}
