<?php

declare(strict_types=1);

namespace App\Actions\Cms;

use App\Data\Cms\NewCmsSection;
use App\Enums\Audit\AuditEvent;
use App\Exceptions\Cms\CmsRejected;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsSection;
use App\Models\Identity\User;
use App\Services\Cms\CmsAuditTrail;
use App\Services\Cms\CmsContentWriter;
use App\Services\Cms\CmsWriteScope;
use App\Services\Platform\ListOrder;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\UniqueConstraintViolationException;

final readonly class CreateCmsSection
{
    public function __construct(
        private CmsWriteScope $scope,
        private CmsContentWriter $writer,
        private ListOrder $order,
        private CmsAuditTrail $audit,
        private Repository $config,
    ) {}

    public function handle(User $actor, CmsPage $page, NewCmsSection $draft): CmsSection
    {
        try {
            return $this->scope->run($page->id, $actor, function (CmsPage $page) use ($actor, $draft): CmsSection {
                $this->assertRoom($page, $draft->key);

                $section = new CmsSection;
                $section->forceFill([
                    'cms_page_id' => $page->id,
                    'key' => $draft->key,
                    'type' => $draft->type,
                    'is_enabled' => $draft->isEnabled,
                    'sort_order' => $this->order->nextPosition($page->sections()->max('sort_order')),
                ]);
                $this->writer->write($section, $draft->content, $actor);
                $this->audit->record(AuditEvent::CmsSectionCreated, $section, [], $this->audit->snapshotOf($section));

                return $section;
            });
        } catch (UniqueConstraintViolationException) {
            throw CmsRejected::sectionKeyTaken();
        }
    }

    private function assertRoom(CmsPage $page, string $key): void
    {
        $limit = $this->config->integer('moashir.cms.sections_per_page_max');

        if ($page->sections()->count() >= $limit) {
            throw CmsRejected::limitReached($limit);
        }

        if ($page->sections()->where('key', $key)->exists()) {
            throw CmsRejected::sectionKeyTaken();
        }
    }
}
