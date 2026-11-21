<?php

declare(strict_types=1);

namespace App\Services\Cms\Landing;

use App\Actions\Cms\UpdateSiteSettings;
use App\Data\Cms\LandingContent;
use App\Data\Cms\SiteSettingsChanges;
use App\Models\Cms\SiteSettings;
use App\Models\Identity\User;

final readonly class LandingLinksReplacer
{
    public function __construct(private SupersededSiteLinks $superseded, private UpdateSiteSettings $update) {}

    /**
     * @return list<string>
     */
    public function replace(User $editor, LandingContent $content): array
    {
        $settings = SiteSettings::current();
        $changes = [];

        if ($this->superseded->isReplaceableHeader($settings->header_links)) {
            $changes['header_links'] = $content->headerLinks;
        }

        if ($this->superseded->isReplaceableQuick($settings->quick_links)) {
            $changes['quick_links'] = $content->quickLinks;
        }

        $this->update->handle($editor, new SiteSettingsChanges($changes));

        return array_keys($changes);
    }
}
