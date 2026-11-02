<?php

declare(strict_types=1);

namespace App\Support\Criteria;

final class CriteriaLinks
{
    private const string ROOT = '/admin/settings/criteria';

    public static function settings(): string
    {
        return self::ROOT;
    }

    public static function main(int $mainId): string
    {
        return self::ROOT.'/'.$mainId;
    }

    public static function sub(int $mainId, int $subId): string
    {
        return self::ROOT.'/'.$mainId.'/subs/'.$subId;
    }
}
