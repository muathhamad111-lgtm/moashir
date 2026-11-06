<?php

declare(strict_types=1);

namespace App\Actions\Cms;

use App\Data\Cms\CmsContentChanges;
use App\Enums\Audit\AuditEvent;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsSection;
use App\Models\Identity\User;
use App\Services\Cms\CmsAuditTrail;
use App\Services\Cms\CmsContentWriter;
use App\Services\Cms\CmsWriteScope;

final readonly class UpdateCmsSection
{
    public function __construct(private CmsWriteScope $scope, private CmsContentWriter $writer, private CmsAuditTrail $audit) {}

    public function handle(User $actor, CmsSection $section, CmsContentChanges $changes): void
    {
        if ($changes->isEmpty()) {
            return;
        }

        $this->scope->run($section->cms_page_id, $actor, function (CmsPage $page) use ($actor, $section, $changes): void {
            $current = CmsSection::query()->whereBelongsTo($page, 'page')->lockForUpdate()->findOrFail($section->id);
            $before = $this->audit->snapshotOf($current);
            $this->writer->write($current, $changes, $actor);
            $this->audit->changed(AuditEvent::CmsSectionUpdated, $current, $before);
        });
    }
}
