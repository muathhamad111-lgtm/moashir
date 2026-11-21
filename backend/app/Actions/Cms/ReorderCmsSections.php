<?php

declare(strict_types=1);

namespace App\Actions\Cms;

use App\Enums\Audit\AuditEvent;
use App\Exceptions\Platform\OrderRejected;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsSection;
use App\Models\Identity\User;
use App\Services\Cms\CmsAuditTrail;
use App\Services\Cms\CmsWriteScope;
use App\Services\Platform\ListOrder;
use App\Support\ScalarValue;

final readonly class ReorderCmsSections
{
    public function __construct(private CmsWriteScope $scope, private ListOrder $order, private CmsAuditTrail $audit) {}

    /**
     * @param  list<int>  $orderedIds
     */
    public function handle(User $actor, array $orderedIds): CmsPage
    {
        $pageId = ScalarValue::int(CmsSection::query()->whereKey($orderedIds[0] ?? 0)->value('cms_page_id'));

        if ($pageId === 0) {
            throw OrderRejected::incomplete();
        }

        return $this->scope->run($pageId, $actor, function (CmsPage $page) use ($orderedIds): CmsPage {
            $sections = $page->sections()->get();
            $before = $sections->pluck('key')->all();
            $this->order->apply($sections, $orderedIds);
            $this->audit->record(AuditEvent::CmsSectionsReordered, $page, ['order' => $before], ['order' => $page->sections()->pluck('key')->all()]);

            return $page;
        });
    }
}
