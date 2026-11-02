<?php

declare(strict_types=1);

namespace App\Services\Criteria;

use App\Events\Criteria\CriteriaWeightsBecameInconsistent;
use App\Exceptions\Criteria\CriteriaVersionRejected;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Identity\User;
use Closure;
use Illuminate\Support\Facades\DB;

final readonly class DraftCriteriaTransaction
{
    public function __construct(
        private CriteriaTreeLoader $loader,
        private CriteriaConsistencyChecker $checker,
    ) {}

    /**
     * @template TResult
     *
     * @param  Closure(CriteriaVersion): TResult  $change
     * @return TResult
     */
    public function run(int $versionId, User $actor, Closure $change): mixed
    {
        return DB::transaction(function () use ($versionId, $actor, $change): mixed {
            $version = $this->lockDraft($versionId);
            $result = $change($version);
            $this->refreshConsistency($version, $actor);

            return $result;
        });
    }

    /**
     * @template TResult
     *
     * @param  Closure(CriteriaVersion): TResult  $change
     * @return TResult
     */
    public function runOnCurrentDraft(User $actor, Closure $change): mixed
    {
        $draftId = CriteriaVersion::query()->draft()->value('id');

        if (! is_int($draftId)) {
            throw CriteriaVersionRejected::noDraft();
        }

        return $this->run($draftId, $actor, $change);
    }

    public function lockDraft(int $versionId): CriteriaVersion
    {
        $version = CriteriaVersion::query()->whereKey($versionId)->lockForUpdate()->firstOrFail();

        if (! $version->status->acceptsStructuralEdits()) {
            throw CriteriaVersionRejected::notDraft($version->status);
        }

        return $version;
    }

    private function refreshConsistency(CriteriaVersion $version, User $actor): void
    {
        $wasConsistent = $version->isConsistent();
        $report = $this->checker->check($this->loader->load($version));

        $version->forceFill(['validation_errors' => $report->toArray()])->save();

        if ($wasConsistent && ! $report->isConsistent()) {
            CriteriaWeightsBecameInconsistent::dispatch($version, $report, $actor->id);
        }
    }
}
