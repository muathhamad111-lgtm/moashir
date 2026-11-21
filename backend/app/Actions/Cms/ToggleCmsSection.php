<?php

declare(strict_types=1);

namespace App\Actions\Cms;

use App\Enums\Audit\AuditEvent;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsSection;
use App\Models\Identity\User;
use App\Services\Cms\CmsAuditTrail;
use App\Services\Cms\CmsWriteScope;

final readonly class ToggleCmsSection
{
    public function __construct(private CmsWriteScope $scope, private CmsAuditTrail $audit) {}

    public function handle(User $actor, CmsSection $section, bool $enabled): void
    {
        $this->scope->run($section->cms_page_id, $actor, function (CmsPage $page) use ($actor, $section, $enabled): void {
            $current = CmsSection::query()->whereBelongsTo($page, 'page')->lockForUpdate()->findOrFail($section->id);

            if ($current->is_enabled === $enabled) {
                return;
            }

            $current->forceFill(['is_enabled' => $enabled, 'updated_by_user_id' => $actor->id])->save();
            $this->audit->record(AuditEvent::CmsSectionToggled, $current, ['enabled' => ! $enabled], ['enabled' => $enabled]);
        });
    }
}
