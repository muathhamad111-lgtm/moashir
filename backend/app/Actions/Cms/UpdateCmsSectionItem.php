<?php

declare(strict_types=1);

namespace App\Actions\Cms;

use App\Data\Cms\CmsContentChanges;
use App\Enums\Audit\AuditEvent;
use App\Models\Cms\CmsSection;
use App\Models\Cms\CmsSectionItem;
use App\Models\Identity\User;
use App\Services\Cms\CmsAuditTrail;
use App\Services\Cms\CmsContentWriter;
use App\Services\Cms\CmsWriteScope;

final readonly class UpdateCmsSectionItem
{
    public function __construct(private CmsWriteScope $scope, private CmsContentWriter $writer, private CmsAuditTrail $audit) {}

    public function handle(User $actor, CmsSection $section, CmsSectionItem $item, CmsContentChanges $changes): void
    {
        if ($changes->isEmpty()) {
            return;
        }

        $this->scope->run($section->cms_page_id, $actor, function () use ($actor, $section, $item, $changes): void {
            $current = CmsSectionItem::query()->whereBelongsTo($section, 'section')->lockForUpdate()->findOrFail($item->id);
            $before = $this->audit->snapshotOf($current);
            $this->writer->write($current, $changes, $actor);
            $this->audit->changed(AuditEvent::CmsItemUpdated, $current, $before);
        });
    }
}
