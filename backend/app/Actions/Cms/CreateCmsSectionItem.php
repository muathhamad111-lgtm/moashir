<?php

declare(strict_types=1);

namespace App\Actions\Cms;

use App\Data\Cms\NewCmsItem;
use App\Enums\Audit\AuditEvent;
use App\Exceptions\Cms\CmsRejected;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsSection;
use App\Models\Cms\CmsSectionItem;
use App\Models\Identity\User;
use App\Services\Cms\CmsAuditTrail;
use App\Services\Cms\CmsContentWriter;
use App\Services\Cms\CmsWriteScope;
use App\Services\Platform\ListOrder;
use Illuminate\Contracts\Config\Repository;

final readonly class CreateCmsSectionItem
{
    public function __construct(
        private CmsWriteScope $scope,
        private CmsContentWriter $writer,
        private ListOrder $order,
        private CmsAuditTrail $audit,
        private Repository $config,
    ) {}

    public function handle(User $actor, CmsSection $section, NewCmsItem $draft): CmsSectionItem
    {
        return $this->scope->run($section->cms_page_id, $actor, function (CmsPage $page) use ($actor, $section, $draft): CmsSectionItem {
            $current = CmsSection::query()->whereBelongsTo($page, 'page')->lockForUpdate()->findOrFail($section->id);
            $limit = $this->config->integer('moashir.cms.items_per_section_max');

            if ($current->items()->count() >= $limit) {
                throw CmsRejected::limitReached($limit);
            }

            $item = new CmsSectionItem;
            $item->forceFill([
                'cms_section_id' => $current->id,
                'is_enabled' => $draft->isEnabled,
                'sort_order' => $this->order->nextPosition($current->items()->max('sort_order')),
            ]);
            $this->writer->write($item, $draft->content, $actor);
            $this->audit->record(AuditEvent::CmsItemCreated, $item, [], $this->audit->snapshotOf($item));

            return $item;
        });
    }
}
