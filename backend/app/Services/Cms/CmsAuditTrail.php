<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Models\Cms\CmsSection;
use App\Models\Cms\CmsSectionItem;
use App\Services\Audit\AuditRecorder;
use Illuminate\Database\Eloquent\Model;

final readonly class CmsAuditTrail
{
    public function __construct(private AuditRecorder $audit, private CmsAuditSnapshot $snapshot) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshotOf(CmsSection|CmsSectionItem $content): array
    {
        return $this->snapshot->of($content);
    }

    /**
     * @param  array<string, mixed>  $before
     */
    public function changed(AuditEvent $event, CmsSection|CmsSectionItem $content, array $before): void
    {
        $this->record($event, $content, $before, $this->snapshot->of($content));
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function record(AuditEvent $event, Model $subject, array $before, array $after): void
    {
        $this->audit->record(new AuditEntry($event, $subject, $before, $after));
    }
}
