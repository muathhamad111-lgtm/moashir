<?php

declare(strict_types=1);

namespace App\Support\Cms;

use App\Enums\Cms\CmsPageKey;

final class ContentLinks
{
    public static function page(CmsPageKey $key): string
    {
        return $key === CmsPageKey::Home ? '/admin/settings/homepage' : '/admin/settings/homepage?page='.$key->value;
    }

    public static function kpis(): string
    {
        return '/admin/kpis';
    }

    public static function kpi(int $kpiId): string
    {
        return '/admin/kpis?kpi='.$kpiId;
    }
}
