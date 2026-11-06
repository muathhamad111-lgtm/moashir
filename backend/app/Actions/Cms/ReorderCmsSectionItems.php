<?php

declare(strict_types=1);

namespace App\Actions\Cms;

use App\Enums\Audit\AuditEvent;
use App\Models\Cms\CmsSection;
use App\Models\Identity\User;
use App\Services\Cms\CmsAuditTrail;
use App\Services\Cms\CmsWriteScope;
use App\Services\Platform\ListOrder;

final readonly class ReorderCmsSectionItems
{
    public function __construct(private CmsWriteScope $scope, private ListOrder $order, private CmsAuditTrail $audit) {}

    /**
     * @param  list<int>  $orderedIds
     */
    public function handle(User $actor, CmsSection $section, array $orderedIds): void
    {
        $this->scope->run($section->cms_page_id, $actor, function () use ($section, $orderedIds): void {
            $items = $section->items()->get();
            $before = $items->modelKeys();
            $this->order->apply($items, $orderedIds);
            $this->audit->record(AuditEvent::CmsItemsReordered, $section, ['order' => $before], ['order' => $section->items()->pluck('id')->all()]);
        });
    }
}
