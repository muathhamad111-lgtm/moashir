<?php

declare(strict_types=1);

namespace App\Listeners\Cms;

use App\Data\Cms\ContentNotice;
use App\Enums\Cms\CmsPageKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Cms\CmsPagePublished;
use App\Events\Cms\CmsPageUnpublished;
use App\Events\Cms\SiteSettingsUpdated;
use App\Events\Kpis\KpiCreated;
use App\Events\Kpis\KpiPublished;
use App\Events\Kpis\KpiUnpublished;
use App\Events\Kpis\KpiValueChanged;
use App\Services\Cms\ContentNotifier;
use App\Support\Cms\ContentLinks;
use App\Support\ValueObjects\LocalizedText;

final readonly class NotifyContentTeam
{
    public function __construct(private ContentNotifier $notifier) {}

    public function handle(CmsPagePublished|CmsPageUnpublished|SiteSettingsUpdated|KpiCreated|KpiValueChanged|KpiPublished|KpiUnpublished $event): void
    {
        $this->notifier->notify($this->noticeFor($event), $event->actorId);
    }

    private function noticeFor(CmsPagePublished|CmsPageUnpublished|SiteSettingsUpdated|KpiCreated|KpiValueChanged|KpiPublished|KpiUnpublished $event): ContentNotice
    {
        return match (true) {
            $event instanceof CmsPagePublished => ContentNotice::for(NotificationCode::HomepageContentEdited, ['page' => $this->pageName($event->page->value)], ContentLinks::page($event->page)),
            $event instanceof CmsPageUnpublished => ContentNotice::for(NotificationCode::ContentUnpublished, ['content' => $this->pageName($event->page->value)], ContentLinks::page($event->page)),
            $event instanceof SiteSettingsUpdated => ContentNotice::for(NotificationCode::HomepageContentEdited, ['page' => LocalizedText::fromTranslation('notifications.content.site_settings')], ContentLinks::page(CmsPageKey::Home)),
            $event instanceof KpiCreated => ContentNotice::for(NotificationCode::KpiAdded, ['name' => $this->kpiName($event)], ContentLinks::kpi($event->kpi->id)),
            $event instanceof KpiValueChanged => ContentNotice::for(NotificationCode::KpiValueEdited, ['name' => $this->kpiName($event)], ContentLinks::kpi($event->kpi->id)),
            $event instanceof KpiPublished => ContentNotice::for(NotificationCode::KpiPublished, ['name' => $this->kpiName($event)], ContentLinks::kpi($event->kpi->id)),
            default => ContentNotice::for(NotificationCode::ContentUnpublished, ['content' => $this->kpiName($event)], ContentLinks::kpi($event->kpi->id)),
        };
    }

    private function pageName(string $key): LocalizedText
    {
        return LocalizedText::fromTranslation('notifications.content.pages.'.$key);
    }

    private function kpiName(KpiCreated|KpiValueChanged|KpiPublished|KpiUnpublished $event): LocalizedText
    {
        $name = $event->kpi->name->withFallback();

        return new LocalizedText($name['ar'], $name['en']);
    }
}
