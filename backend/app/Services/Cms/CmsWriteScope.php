<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Models\Cms\CmsPage;
use App\Models\Identity\User;
use App\Services\Platform\TransactionLock;
use Closure;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class CmsWriteScope
{
    public function __construct(private TransactionLock $lock) {}

    /**
     * @template TResult
     *
     * @param  Closure(CmsPage): TResult  $work
     * @return TResult
     */
    public function run(int $pageId, User $actor, Closure $work): mixed
    {
        return $this->locked($pageId, static function (CmsPage $page) use ($actor, $work): mixed {
            $result = $work($page);
            $page->forceFill(['content_updated_at' => Date::now(), 'has_draft_changes' => true, 'updated_by_user_id' => $actor->id])->save();

            return $result;
        });
    }

    /**
     * @template TResult
     *
     * @param  Closure(CmsPage): TResult  $work
     * @return TResult
     */
    public function locked(int $pageId, Closure $work): mixed
    {
        return DB::transaction(function () use ($pageId, $work): mixed {
            $this->lock->lock('cms:page:'.$pageId);

            return $work(CmsPage::query()->findOrFail($pageId));
        });
    }
}
