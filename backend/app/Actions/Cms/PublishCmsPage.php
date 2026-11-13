<?php

declare(strict_types=1);

namespace App\Actions\Cms;

use App\Enums\Audit\AuditEvent;
use App\Events\Cms\CmsPagePublished;
use App\Exceptions\Cms\CmsRejected;
use App\Models\Cms\CmsPage;
use App\Models\Identity\User;
use App\Services\Cms\CmsAuditTrail;
use App\Services\Cms\CmsImageJanitor;
use App\Services\Cms\CmsSnapshotBuilder;
use App\Services\Cms\CmsWriteScope;
use Illuminate\Support\Facades\Date;

final readonly class PublishCmsPage
{
    public function __construct(
        private CmsWriteScope $scope,
        private CmsSnapshotBuilder $snapshots,
        private CmsImageJanitor $janitor,
        private CmsAuditTrail $audit,
    ) {}

    public function handle(User $actor, CmsPage $page): void
    {
        $this->scope->locked($page->id, function (CmsPage $current) use ($actor): void {
            $snapshot = $this->snapshots->build($current);
            $sectionCount = is_array($snapshot['sections'] ?? null) ? count($snapshot['sections']) : 0;

            if ($sectionCount === 0) {
                throw CmsRejected::pageEmpty();
            }

            $wasPublished = $current->is_published;
            $previousAt = $current->published_at?->toIso8601String();
            $current->forceFill([
                'is_published' => true,
                'published_content' => $snapshot,
                'published_at' => Date::now(),
                'published_by_user_id' => $actor->id,
                'has_draft_changes' => false,
            ])->save();
            $removedImages = $this->janitor->removeUnreferenced($current);

            $this->audit->record(AuditEvent::CmsPagePublished, $current, ['published' => $wasPublished, 'published_at' => $previousAt], [
                'published' => true,
                'published_at' => $current->published_at?->toIso8601String(),
                'sections' => $sectionCount,
                'removed_images' => $removedImages,
            ]);

            CmsPagePublished::dispatch($current->key, $actor->id);
        });
    }
}
