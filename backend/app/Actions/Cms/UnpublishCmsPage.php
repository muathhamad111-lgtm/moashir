<?php

declare(strict_types=1);

namespace App\Actions\Cms;

use App\Enums\Audit\AuditEvent;
use App\Events\Cms\CmsPageUnpublished;
use App\Exceptions\IllegalStateTransition;
use App\Models\Cms\CmsPage;
use App\Models\Identity\User;
use App\Services\Cms\CmsAuditTrail;
use App\Services\Cms\CmsWriteScope;

final readonly class UnpublishCmsPage
{
    public function __construct(private CmsWriteScope $scope, private CmsAuditTrail $audit) {}

    public function handle(User $actor, CmsPage $page): void
    {
        $this->scope->locked($page->id, function (CmsPage $current) use ($actor): void {
            if (! $current->is_published) {
                throw IllegalStateTransition::from('unpublished', 'unpublish');
            }

            $current->forceFill(['is_published' => false, 'updated_by_user_id' => $actor->id])->save();
            $this->audit->record(AuditEvent::CmsPageUnpublished, $current, ['published' => true], ['published' => false]);

            CmsPageUnpublished::dispatch($current->key, $actor->id);
        });
    }
}
