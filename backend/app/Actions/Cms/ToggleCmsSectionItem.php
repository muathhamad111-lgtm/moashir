<?php

declare(strict_types=1);

namespace App\Actions\Cms;

use App\Enums\Audit\AuditEvent;
use App\Models\Cms\CmsSection;
use App\Models\Cms\CmsSectionItem;
use App\Models\Identity\User;
use App\Services\Cms\CmsAuditTrail;
use App\Services\Cms\CmsWriteScope;

final readonly class ToggleCmsSectionItem
{
    public function __construct(private CmsWriteScope $scope, private CmsAuditTrail $audit) {}

    public function handle(User $actor, CmsSection $section, CmsSectionItem $item, bool $enabled): void
    {
        $this->scope->run($section->cms_page_id, $actor, function () use ($actor, $section, $item, $enabled): void {
            $current = CmsSectionItem::query()->whereBelongsTo($section, 'section')->lockForUpdate()->findOrFail($item->id);

            if ($current->is_enabled === $enabled) {
                return;
            }

            $current->forceFill(['is_enabled' => $enabled, 'updated_by_user_id' => $actor->id])->save();
            $this->audit->record(AuditEvent::CmsItemToggled, $current, ['enabled' => ! $enabled], ['enabled' => $enabled]);
        });
    }
}
