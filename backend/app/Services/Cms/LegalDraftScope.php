<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Enums\Identity\TermsKind;
use App\Exceptions\Cms\LegalTextRejected;
use App\Models\Identity\TermsVersion;
use App\Services\Platform\TransactionLock;
use Closure;
use Illuminate\Support\Facades\DB;

final readonly class LegalDraftScope
{
    public function __construct(private TransactionLock $lock) {}

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $work
     * @return TResult
     */
    public function forKind(TermsKind $kind, Closure $work): mixed
    {
        return DB::transaction(function () use ($kind, $work): mixed {
            $this->lock->lock('terms:'.$kind->value);

            return $work();
        });
    }

    /**
     * @template TResult
     *
     * @param  Closure(TermsVersion): TResult  $work
     * @return TResult
     */
    public function onDraft(TermsVersion $version, Closure $work): mixed
    {
        return $this->forKind($version->termsKind(), static function () use ($version, $work): mixed {
            $locked = TermsVersion::query()->lockForUpdate()->findOrFail($version->id);

            if (! $locked->isDraft()) {
                throw LegalTextRejected::notDraft($locked->status());
            }

            return $work($locked);
        });
    }

    public function assertVersionFree(TermsKind $kind, string $version, ?int $ignoreId = null): void
    {
        $taken = TermsVersion::query()->ofKind($kind)->where('version', $version)
            ->when($ignoreId !== null, static fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($taken) {
            throw LegalTextRejected::versionTaken();
        }
    }
}
