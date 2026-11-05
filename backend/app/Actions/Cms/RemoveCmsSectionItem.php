<?php

declare(strict_types=1);

namespace App\Actions\Cms;

use App\Enums\Audit\AuditEvent;
use App\Models\Cms\CmsSection;
use App\Models\Cms\CmsSectionItem;
use App\Models\Identity\User;
use App\Services\Cms\CmsAuditTrail;
use App\Services\Cms\CmsWriteScope;
use App\Services\Platform\ListOrder;

final readonly class RemoveCmsSectionItem
{
    public function __construct(private CmsWriteScope $scope, private ListOrder $order, private CmsAuditTrail $audit) {}

    public function handle(User $actor, CmsSection $section, CmsSectionItem $item): void
    {
        $this->scope->run($section->cms_page_id, $actor, function () use ($section, $item): void {
            $current = CmsSectionItem::query()->whereBelongsTo($section, 'section')->lockForUpdate()->findOrFail($item->id);
            $before = $this->audit->snapshotOf($current);
            $current->delete();
            $this->order->resequence($section->items()->get());
            $this->audit->record(AuditEvent::CmsItemRemoved, $current, $before, []);
        });
    }
}
