<?php

declare(strict_types=1);

namespace App\Actions\Cms;

use App\Data\Cms\CmsPageChanges;
use App\Enums\Audit\AuditEvent;
use App\Models\Cms\CmsPage;
use App\Models\Identity\User;
use App\Services\Cms\CmsAuditTrail;
use App\Services\Cms\CmsWriteScope;

final readonly class UpdateCmsPageDetails
{
    public function __construct(private CmsWriteScope $scope, private CmsAuditTrail $audit) {}

    public function handle(User $actor, CmsPage $page, CmsPageChanges $changes): void
    {
        if ($changes->isEmpty()) {
            return;
        }

        $this->scope->run($page->id, $actor, function (CmsPage $current) use ($changes): void {
            $before = ['title' => $current->title?->toArray(), 'seo' => $current->seo];
            $current->fill($changes->attributes)->save();
            $this->audit->record(AuditEvent::CmsPageUpdated, $current, $before, ['title' => $current->title?->toArray(), 'seo' => $current->seo]);
        });
    }
}
