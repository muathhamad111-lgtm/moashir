<?php

declare(strict_types=1);

namespace App\Listeners\Cms;

use App\Events\Cms\CmsPagePublished;
use App\Events\Cms\CmsPageUnpublished;
use App\Events\Cms\SiteSettingsUpdated;
use App\Events\Identity\TermsVersionPublished;
use App\Services\Cms\PublicContentCache;

final readonly class ForgetPublicContent
{
    public function __construct(private PublicContentCache $cache) {}

    public function handle(CmsPagePublished|CmsPageUnpublished|SiteSettingsUpdated|TermsVersionPublished $event): void
    {
        match (true) {
            $event instanceof SiteSettingsUpdated => $this->cache->forgetSiteSettings(),
            $event instanceof TermsVersionPublished => $this->cache->forgetLegalText($event->kind),
            default => $this->cache->forgetPage($event->page),
        };
    }
}
