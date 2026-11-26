<?php

declare(strict_types=1);

namespace App\Actions\Cms;

use App\Data\Audit\AuditEntry;
use App\Data\Cms\LegalDraftChanges;
use App\Enums\Audit\AuditEvent;
use App\Models\Identity\TermsVersion;
use App\Services\Audit\AuditRecorder;
use App\Services\Cms\LegalDraftScope;
use App\Services\Identity\TermsVersionSnapshot;

final readonly class UpdateLegalDraft
{
    public function __construct(
        private LegalDraftScope $scope,
        private AuditRecorder $audit,
        private TermsVersionSnapshot $snapshot,
    ) {}

    public function handle(TermsVersion $draft, LegalDraftChanges $changes): TermsVersion
    {
        return $this->scope->onDraft($draft, function (TermsVersion $locked) use ($changes): TermsVersion {
            $attributes = $changes->attributes();

            if ($attributes === []) {
                return $locked;
            }

            if ($changes->version !== null) {
                $this->scope->assertVersionFree($locked->termsKind(), $changes->version, $locked->id);
            }

            $before = $this->snapshot->of($locked);
            $locked->fill($attributes)->save();
            $this->audit->record(new AuditEntry(AuditEvent::TermsDraftUpdated, $locked, $before, $this->snapshot->of($locked)));

            return $locked;
        });
    }
}
