<?php

declare(strict_types=1);

namespace App\Actions\Cms;

use App\Enums\Cms\CmsPageKey;
use App\Models\Cms\CmsPage;

final class GetCmsHomePage
{
    public function handle(): CmsPage
    {
        return CmsPage::query()->where('key', CmsPageKey::Home)->firstOrFail();
    }
}
