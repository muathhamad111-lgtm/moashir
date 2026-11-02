<?php

declare(strict_types=1);

namespace App\Actions\Criteria;

use App\Enums\Audit\AuditEvent;
use App\Exceptions\Criteria\CriteriaVersionRejected;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Identity\User;
use App\Services\Criteria\CriteriaAuditTrail;
use App\Services\Criteria\CriteriaConsistencyChecker;
use App\Services\Criteria\CriteriaTreeLoader;
use App\Services\Criteria\CriteriaVersionCloner;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final readonly class CreateDraftVersion
{
    public function __construct(
        private CriteriaTreeLoader $loader,
        private CriteriaVersionCloner $cloner,
        private CriteriaConsistencyChecker $checker,
        private CriteriaAuditTrail $audit,
    ) {}

    public function handle(User $actor, ?int $cloneFromVersionId, ?LocalizedText $label): CriteriaVersion
    {
        try {
            return DB::transaction(function () use ($cloneFromVersionId, $label): CriteriaVersion {
                $this->assertNoOpenDraft();
                $source = $this->sourceFor($cloneFromVersionId);
                $draft = $this->newDraft($source, $label);

                if ($source instanceof CriteriaVersion) {
                    $this->cloner->cloneInto($this->loader->load($source), $draft);
                }

                $report = $this->checker->check($this->loader->load($draft));
                $draft->forceFill(['validation_errors' => $report->toArray()])->save();
                $this->audit->record(AuditEvent::CriteriaVersionCreated, $draft, [], [
                    'version_number' => $draft->version_number,
                    'cloned_from_version_id' => $draft->cloned_from_version_id,
                ]);

                return $draft;
            });
        } catch (UniqueConstraintViolationException) {
            throw CriteriaVersionRejected::draftExists();
        }
    }

    private function assertNoOpenDraft(): void
    {
        if (CriteriaVersion::query()->draft()->lockForUpdate()->exists()) {
            throw CriteriaVersionRejected::draftExists();
        }
    }

    private function sourceFor(?int $cloneFromVersionId): ?CriteriaVersion
    {
        if ($cloneFromVersionId === null) {
            return CriteriaVersion::query()->published()->first();
        }

        $source = CriteriaVersion::query()->find($cloneFromVersionId);

        if (! $source instanceof CriteriaVersion || $source->isDraft()) {
            throw CriteriaVersionRejected::cloneSourceInvalid();
        }

        return $source;
    }

    private function newDraft(?CriteriaVersion $source, ?LocalizedText $label): CriteriaVersion
    {
        $highest = CriteriaVersion::withTrashed()->max('version_number');

        return CriteriaVersion::query()->create([
            'version_number' => (is_numeric($highest) ? (int) $highest : 0) + 1,
            'label' => $label ?? $source?->label,
            'cloned_from_version_id' => $source?->id,
        ]);
    }
}
