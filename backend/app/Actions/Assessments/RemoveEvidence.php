<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\CompletionItemKind;
use App\Enums\Assessments\EvidenceStatus;
use App\Enums\Audit\AuditEvent;
use App\Events\Assessments\AccreditedDraftSaved;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\EvidenceFile;
use App\Models\Identity\User;
use App\Services\Assessments\CompletionScope;
use App\Services\Assessments\EntityContentGate;
use App\Services\Audit\AuditRecorder;
use App\Services\Files\StoredFileRemover;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class RemoveEvidence
{
    public function __construct(
        private EntityContentGate $gate,
        private CompletionScope $completion,
        private StoredFileRemover $remover,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, Assessment $assessment, EvidenceFile $evidence, ?string $reason): void
    {
        DB::transaction(function () use ($actor, $assessment, $evidence, $reason): void {
            $locked = $this->gate->enter($actor, $assessment, $reason);
            $current = EvidenceFile::query()->with('storedFile')->whereKey($evidence->id)->lockForUpdate()->first();

            if (! $current instanceof EvidenceFile || ! $current->isPresent()) {
                throw (new ModelNotFoundException)->setModel(EvidenceFile::class);
            }

            $this->completion->assertRequested($locked, $current->sub_code, CompletionItemKind::Evidence, 'evidence');
            $this->remove($actor, $locked, $current);
        });
    }

    private function remove(User $actor, Assessment $locked, EvidenceFile $evidence): void
    {
        $evidence->forceFill(['status' => EvidenceStatus::Removed, 'removed_at' => Date::now(), 'removed_by_user_id' => $actor->id])->save();
        $stored = $evidence->storedFile;
        DB::afterCommit(fn () => $this->remover->remove($stored));
        $locked->touch();

        $this->audit->record(new AuditEntry(AuditEvent::EvidenceRemoved, $locked, ['evidence_id' => $evidence->public_id], [
            'evidence_id' => $evidence->public_id,
            'sub_code' => $evidence->sub_code,
            'status' => EvidenceStatus::Removed->value,
        ]));

        AccreditedDraftSaved::dispatch($locked);
    }
}
