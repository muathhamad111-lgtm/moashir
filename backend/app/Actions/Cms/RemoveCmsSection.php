<?php

declare(strict_types=1);

namespace App\Actions\Cms;

use App\Enums\Audit\AuditEvent;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsSection;
use App\Models\Identity\User;
use App\Services\Cms\CmsAuditTrail;
use App\Services\Cms\CmsWriteScope;
use App\Services\Platform\ListOrder;

final readonly class RemoveCmsSection
{
    public function __construct(private CmsWriteScope $scope, private ListOrder $order, private CmsAuditTrail $audit) {}

    public function handle(User $actor, CmsSection $section): void
    {
        $this->scope->run($section->cms_page_id, $actor, function (CmsPage $page) use ($section): void {
            $current = CmsSection::query()->whereBelongsTo($page, 'page')->lockForUpdate()->findOrFail($section->id);
            $before = $this->audit->snapshotOf($current);
            $current->delete();
            $this->order->resequence($page->sections()->get());
            $this->audit->record(AuditEvent::CmsSectionRemoved, $current, $before, []);
        });
    }
}
