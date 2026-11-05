<?php

declare(strict_types=1);

namespace App\Actions\Cms;

use App\Data\Audit\AuditEntry;
use App\Data\Cms\LegalDraft;
use App\Enums\Audit\AuditEvent;
use App\Exceptions\Cms\LegalTextRejected;
use App\Models\Identity\TermsVersion;
use App\Services\Audit\AuditRecorder;
use App\Services\Cms\LegalDraftScope;
use App\Services\Identity\TermsVersionSnapshot;

final readonly class CreateLegalDraft
{
    public function __construct(
        private LegalDraftScope $scope,
        private AuditRecorder $audit,
        private TermsVersionSnapshot $snapshot,
    ) {}

    public function handle(LegalDraft $draft): TermsVersion
    {
        return $this->scope->forKind($draft->kind, function () use ($draft): TermsVersion {
            if (TermsVersion::query()->ofKind($draft->kind)->drafts()->exists()) {
                throw LegalTextRejected::draftExists();
            }

            $this->scope->assertVersionFree($draft->kind, $draft->version);
            $version = TermsVersion::query()->create([
                'kind' => $draft->kind->value,
                'version' => $draft->version,
                'title' => $draft->title,
                'body' => $draft->body,
                'is_current' => false,
            ]);
            $this->audit->record(new AuditEntry(AuditEvent::TermsDraftCreated, $version, [], $this->snapshot->of($version)));

            return $version;
        });
    }
}
