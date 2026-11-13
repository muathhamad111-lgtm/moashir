<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Data\Audit\AuditEntry;
use App\Data\Identity\TermsPublication;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\TermsKind;
use App\Events\Identity\TermsVersionPublished;
use App\Exceptions\Cms\LegalTextRejected;
use App\Models\Identity\TermsVersion;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Platform\TransactionLock;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class TermsVersionPublisher
{
    public function __construct(
        private TransactionLock $lock,
        private AuditRecorder $audit,
        private TermsVersionSnapshot $snapshot,
    ) {}

    public function publishNew(User $publisher, TermsPublication $publication): TermsVersion
    {
        return $this->publishing($publication->kind, static fn (): TermsVersion => TermsVersion::query()->forceCreate([
            'kind' => $publication->kind->value,
            'version' => $publication->version,
            'title' => $publication->title,
            'body' => $publication->body,
            'effective_from' => $publication->effectiveFrom->toDateString(),
            'is_current' => true,
            'published_at' => Date::now(),
            'published_by_user_id' => $publisher->id,
        ]));
    }

    public function publishDraft(User $publisher, TermsVersion $draft, CarbonImmutable $effectiveFrom): TermsVersion
    {
        return $this->publishing($draft->termsKind(), static function () use ($publisher, $draft, $effectiveFrom): TermsVersion {
            $locked = TermsVersion::query()->lockForUpdate()->findOrFail($draft->id);

            if (! $locked->isDraft()) {
                throw LegalTextRejected::notDraft($locked->status());
            }

            $locked->forceFill([
                'effective_from' => $effectiveFrom->toDateString(),
                'is_current' => true,
                'published_at' => Date::now(),
                'published_by_user_id' => $publisher->id,
            ])->save();

            return $locked;
        });
    }

    /**
     * @param  Closure(): TermsVersion  $write
     */
    private function publishing(TermsKind $kind, Closure $write): TermsVersion
    {
        return DB::transaction(function () use ($kind, $write): TermsVersion {
            $this->lock->lock('terms:'.$kind->value);
            $previous = TermsVersion::query()->current()->ofKind($kind)->lockForUpdate()->first();
            $previous?->forceFill(['is_current' => false])->save();
            $version = $write();

            $this->audit->record(new AuditEntry(
                AuditEvent::TermsPublished,
                $version,
                ['current_version' => $previous?->version],
                ['current_version' => $version->version, ...$this->snapshot->of($version)],
            ));

            TermsVersionPublished::dispatch($kind);

            return $version;
        });
    }
}
