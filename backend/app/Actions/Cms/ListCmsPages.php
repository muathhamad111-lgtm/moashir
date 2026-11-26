<?php

declare(strict_types=1);

namespace App\Actions\Cms;

use App\Models\Cms\CmsPage;
use Illuminate\Database\Eloquent\Collection;

final class ListCmsPages
{
    /**
     * @return Collection<int, CmsPage>
     */
    public function handle(): Collection
    {
        return CmsPage::query()->orderBy('id')->get();
    }
}
