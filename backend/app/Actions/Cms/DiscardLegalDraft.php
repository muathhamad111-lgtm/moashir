<?php

declare(strict_types=1);

namespace App\Actions\Cms;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Models\Identity\TermsVersion;
use App\Services\Audit\AuditRecorder;
use App\Services\Cms\LegalDraftScope;
use App\Services\Identity\TermsVersionSnapshot;

final readonly class DiscardLegalDraft
{
    public function __construct(
        private LegalDraftScope $scope,
        private AuditRecorder $audit,
        private TermsVersionSnapshot $snapshot,
    ) {}

    public function handle(TermsVersion $draft): void
    {
        $this->scope->onDraft($draft, function (TermsVersion $locked): void {
            $this->audit->record(new AuditEntry(AuditEvent::TermsDraftDiscarded, $locked, $this->snapshot->of($locked), []));
            $locked->delete();
        });
    }
}
